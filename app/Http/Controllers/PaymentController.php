<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    private const MOBILE_PATTERN = '/^(?:\+?88)?01[3-9]\d{8}$/';

    public function __construct(private PaymentService $payments)
    {
    }

    public function index(Request $request)
    {
        $periods = PayrollPeriod::whereIn('status', ['approved', 'paid', 'closed'])
            ->orderByDesc('year')->orderByDesc('month')->get();

        $period = $request->filled('period')
            ? $periods->firstWhere('id', (int) $request->period)
            : $periods->first();

        $data = ['periods' => $periods, 'period' => $period];

        if ($period) {
            $inPeriod = fn ($q) => $q->where('payroll_period_id', $period->id);

            $data += [
                'payments' => Payment::with('employee.department')
                    ->whereHas('payroll', $inPeriod)
                    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                    ->orderBy('employee_id')->orderBy('id')
                    ->paginate(40)->withQueryString(),
                'netTotal' => (float) Payroll::where('payroll_period_id', $period->id)->sum('net_salary'),
                'paidTotal' => (float) Payment::whereHas('payroll', $inPeriod)->where('status', 'paid')->sum('amount'),
                'counts' => Payment::whereHas('payroll', $inPeriod)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
                'payrollCount' => Payroll::where('payroll_period_id', $period->id)->count(),
                'missing' => Payroll::where('payroll_period_id', $period->id)
                    ->whereDoesntHave('payments', fn ($q) => $q->whereIn('status', ['pending', 'paid', 'failed']))->count(),
            ];
        }

        return view('payments.index', $data);
    }

    public function generate(Request $request, PayrollPeriod $payrollPeriod)
    {
        $count = $this->payments->generate($payrollPeriod, $request->user());

        return redirect()->route('payments.index', ['period' => $payrollPeriod->id])->with(
            'success',
            $count > 0 ? "{$count} payment(s) are ready to be recorded." : 'Every employee already has a payment line.'
        );
    }

    public function pay(Request $request, Payment $payment)
    {
        $payment->load('payroll.period');
        $method = $request->input('method');

        $accountRules = ['nullable', 'string', 'max:40'];
        if ($method === 'bank_transfer') {
            $accountRules = ['required', 'regex:/^[0-9]{8,20}$/'];
        } elseif ($method === 'mfs') {
            $accountRules = ['required', 'regex:' . self::MOBILE_PATTERN];
        }

        $needsReference = in_array($method, ['bank_transfer', 'mfs'], true);

        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(PaymentService::METHODS))],
            'bank_name' => [$needsReference ? 'required' : 'nullable', 'string', 'max:100'],
            'account_number' => $accountRules,
            'transaction_reference' => [$needsReference ? 'required' : 'nullable', 'string', 'max:80'],
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:' . $payment->payroll->period->start_date->toDateString()],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], $this->messages());

        $this->payments->pay($payment, $request->user(), $data);

        return back()->with('success', "Payment to {$payment->employee->full_name} has been recorded.");
    }

    public function payMany(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:payments,id'],
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:80'],
        ], [
            'ids.required' => 'Please tick the payments you want to mark as paid.',
            'payment_date.required' => 'Please choose the payment date.',
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
        ]);

        $count = $this->payments->payMany($data['ids'], $request->user(), $data['payment_date'], $data['reference'] ?? null);

        return back()->with('success', "{$count} payment(s) have been marked as paid.");
    }

    public function fail(Request $request, Payment $payment)
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'min:3', 'max:255']], ['remarks.required' => 'Please write what went wrong.']);

        $this->payments->markFailed($payment, $request->user(), $data['remarks']);

        return back()->with('success', 'The payment has been marked as failed. You can record it again once it is sorted out.');
    }

    public function cancel(Request $request, Payment $payment)
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'min:3', 'max:255']], ['remarks.required' => 'Please write why the payment is cancelled.']);

        $this->payments->cancel($payment, $request->user(), $data['remarks']);

        return back()->with('success', 'The payment has been cancelled. Use "Prepare payment list" to create a new one.');
    }

    private function messages(): array
    {
        return [
            'method.required' => 'Please choose how the salary was paid.',
            'bank_name.required' => 'Please enter the bank or mobile service name.',
            'account_number.required' => 'Please enter the account number.',
            'account_number.regex' => 'Enter a valid account number (8 to 20 digits) or, for mobile services, a valid mobile number such as 01712345678.',
            'transaction_reference.required' => 'Please enter the transaction reference.',
            'payment_date.required' => 'Please choose the payment date.',
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
            'payment_date.after_or_equal' => 'The payment date cannot be before the payroll month started.',
        ];
    }
}
