<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Salary payments. Each approved payroll row gets one payment line (amount = net salary).
 * When every payroll row of a period has a Paid payment, the period itself becomes Paid.
 */
class PaymentService
{
    public const METHODS = [
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash',
        'mfs' => 'Mobile financial service (bKash, Nagad ...)',
        'other' => 'Other',
    ];

    /**
     * Creates a Pending payment for every payroll row that has none yet.
     * Bank details are copied from the employee's profile; employees without them default to cash.
     */
    public function generate(PayrollPeriod $period, User $by): int
    {
        return DB::transaction(function () use ($period, $by) {
            $locked = $this->lockPeriod($period, 'Payments can only be prepared for an approved payroll.');

            $payrolls = Payroll::with('employee')
                ->where('payroll_period_id', $locked->id)
                ->whereDoesntHave('payments', fn ($q) => $q->whereIn('status', ['pending', 'paid', 'failed']))
                ->get();

            foreach ($payrolls as $payroll) {
                $employee = $payroll->employee;
                $hasBank = $employee->bank_name && $employee->bank_account_number;

                Payment::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $payroll->employee_id,
                    'method' => $hasBank ? 'bank_transfer' : 'cash',
                    'bank_name' => $hasBank ? $employee->bank_name : null,
                    'account_number' => $hasBank ? $employee->bank_account_number : null,
                    'amount' => $payroll->net_salary,
                    'status' => 'pending',
                ]);
            }

            if ($payrolls->isNotEmpty()) {
                AuditService::log('generated', 'payments', "Payment list for {$locked->label} was prepared ({$payrolls->count()} payments)", $locked->id, $by->id);
            }

            return $payrolls->count();
        });
    }

    /** Records one salary as paid. $data: method, bank_name, account_number, transaction_reference, payment_date, remarks. */
    public function pay(Payment $payment, User $by, array $data): void
    {
        DB::transaction(fn () => $this->markPaid($payment, $by, $data));
    }

    /**
     * Pays several lines at once with their saved method and details and one common reference
     * (a bank batch number). All lines are saved together or not at all.
     */
    public function payMany(array $paymentIds, User $by, string $paymentDate, ?string $reference): int
    {
        return DB::transaction(function () use ($paymentIds, $by, $paymentDate, $reference) {
            $payments = Payment::with('employee')->whereIn('id', $paymentIds)->orderBy('id')->get();

            foreach ($payments as $payment) {
                $needsAccount = in_array($payment->method, ['bank_transfer', 'mfs'], true);

                if ($needsAccount && ! $payment->account_number) {
                    throw new BusinessRuleException("{$payment->employee->full_name} has no account number saved. Record that payment on its own.");
                }

                if ($needsAccount && ! $reference) {
                    throw new BusinessRuleException('Please enter a batch reference, because bank and mobile payments need a transaction reference.');
                }

                $this->markPaid($payment, $by, [
                    'method' => $payment->method,
                    'bank_name' => $payment->bank_name,
                    'account_number' => $payment->account_number,
                    'transaction_reference' => $reference,
                    'payment_date' => $paymentDate,
                    'remarks' => null,
                ]);
            }

            return $payments->count();
        });
    }

    public function markFailed(Payment $payment, User $by, string $remarks): void
    {
        DB::transaction(function () use ($payment, $by, $remarks) {
            $locked = $this->lockPayment($payment, ['pending']);
            $this->lockPeriod($locked->payroll->period, 'Payments can only be changed while the payroll is approved.');

            $locked->update(['status' => 'failed', 'remarks' => $remarks, 'recorded_by' => $by->id]);

            AuditService::log('failed', 'payments', "Payment to {$locked->employee->full_name} for {$locked->payroll->period->label} failed: {$remarks}", $locked->id, $by->id);
        });
    }

    public function cancel(Payment $payment, User $by, string $remarks): void
    {
        DB::transaction(function () use ($payment, $by, $remarks) {
            $locked = $this->lockPayment($payment, ['pending', 'failed']);
            $this->lockPeriod($locked->payroll->period, 'Payments can only be changed while the payroll is approved.');

            $locked->update(['status' => 'cancelled', 'remarks' => $remarks, 'recorded_by' => $by->id]);

            AuditService::log('cancelled', 'payments', "Payment to {$locked->employee->full_name} for {$locked->payroll->period->label} was cancelled: {$remarks}", $locked->id, $by->id);
        });
    }

    private function markPaid(Payment $payment, User $by, array $data): void
    {
        $locked = $this->lockPayment($payment, ['pending', 'failed']);
        $period = $this->lockPeriod($locked->payroll->period, 'Payments can only be recorded while the payroll is approved.');

        $locked->update([
            'method' => $data['method'],
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'payment_date' => $data['payment_date'],
            'remarks' => $data['remarks'] ?? null,
            'status' => 'paid',
            'recorded_by' => $by->id,
        ]);

        AuditService::log(
            'paid', 'payments',
            sprintf('Salary of ৳%s paid to %s for %s (%s)', number_format((float) $locked->amount, 2), $locked->employee->full_name, $period->label, self::METHODS[$data['method']] ?? $data['method']),
            $locked->id, $by->id
        );

        $this->completePeriodIfAllPaid($period, $by);
    }

    /** The period becomes Paid once every payroll row has a Paid payment. */
    private function completePeriodIfAllPaid(PayrollPeriod $period, User $by): void
    {
        $unpaid = Payroll::where('payroll_period_id', $period->id)
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'paid'))
            ->exists();

        if (! $unpaid) {
            $period->update(['status' => 'paid']);
            AuditService::log('paid', 'payroll', "All salaries for {$period->label} have been paid", $period->id, $by->id);
        }
    }

    private function lockPayment(Payment $payment, array $allowed): Payment
    {
        $locked = Payment::whereKey($payment->id)->lockForUpdate()->with('payroll.period', 'employee')->firstOrFail();

        if (! in_array($locked->status, $allowed, true)) {
            throw new BusinessRuleException("This payment is already {$locked->status}.");
        }

        return $locked;
    }

    private function lockPeriod(PayrollPeriod $period, string $message): PayrollPeriod
    {
        $locked = PayrollPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== 'approved') {
            throw new BusinessRuleException($message . " (It is currently {$locked->status}.)");
        }

        return $locked;
    }
}
