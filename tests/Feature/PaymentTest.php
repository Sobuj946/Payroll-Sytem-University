<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    private User $admin;
    private User $officer;
    private Employee $employee;
    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        [$this->admin, $this->employee, $this->period] = $this->payrollScenario();
        $this->employee->update(['bank_name' => 'BRAC Bank', 'bank_account_number' => '1234567890123']);
        $this->officer = $this->userWithRole('payroll_officer');
    }

    private function approved(): void
    {
        $this->approvePayroll($this->period, $this->admin);
    }

    private function generate(): void
    {
        $this->actingAs($this->officer)->post(route('payments.generate', $this->period))->assertSessionHasNoErrors();
    }

    private function bankPayload(array $overrides = []): array
    {
        return array_merge([
            'method' => 'bank_transfer',
            'bank_name' => 'BRAC Bank',
            'account_number' => '1234567890123',
            'transaction_reference' => 'TRX-1001',
            'payment_date' => '2026-10-05',
        ], $overrides);
    }

    /** TC-PMT-001: Payment lines are prepared from an approved payroll */
    public function test_payment_lines_are_prepared(): void
    {
        $this->approved();
        $this->generate();

        $payment = Payment::firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('bank_transfer', $payment->method);
        $this->assertMoney(65000, $payment->amount);
        $this->assertSame('BRAC Bank', $payment->bank_name);
    }

    /** TC-PMT-002: Employees without bank details default to cash */
    public function test_no_bank_details_means_cash(): void
    {
        $this->employee->update(['bank_name' => null, 'bank_account_number' => null]);
        $this->approved();
        $this->generate();

        $this->assertSame('cash', Payment::firstOrFail()->method);
    }

    /** TC-PMT-003: Preparing payments twice does not create duplicates */
    public function test_generating_twice_does_not_duplicate(): void
    {
        $this->approved();
        $this->generate();
        $this->generate();

        $this->assertSame(1, Payment::count());
    }

    /** TC-PMT-004: Payments cannot be prepared before the payroll is approved */
    public function test_payments_need_an_approved_payroll(): void
    {
        $this->processPayroll($this->period, $this->admin);

        $this->actingAs($this->officer)->post(route('payments.generate', $this->period))->assertSessionHas('error');

        $this->assertSame(0, Payment::count());
    }

    /** TC-PMT-005: Record a bank payment; the period becomes Paid when everyone is paid */
    public function test_recording_payment_completes_the_period(): void
    {
        $this->approved();
        $this->generate();
        $payment = Payment::firstOrFail();

        $this->actingAs($this->officer)->post(route('payments.pay', $payment), $this->bankPayload())->assertSessionHasNoErrors();

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('TRX-1001', $payment->transaction_reference);
        $this->assertSame('paid', $this->period->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['module' => 'payments', 'action' => 'paid']);
    }

    /** TC-PMT-006: A bank payment needs a reference and a valid account number */
    public function test_bank_payment_details_are_required(): void
    {
        $this->approved();
        $this->generate();
        $payment = Payment::firstOrFail();

        $this->actingAs($this->officer)->from('/payments')->post(route('payments.pay', $payment), $this->bankPayload(['transaction_reference' => '']))
            ->assertSessionHasErrors('transaction_reference');
        $this->actingAs($this->officer)->from('/payments')->post(route('payments.pay', $payment), $this->bankPayload(['account_number' => '12ab']))
            ->assertSessionHasErrors('account_number');

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** TC-PMT-007: A mobile-service payment needs a valid Bangladesh mobile number */
    public function test_mobile_payment_needs_a_valid_number(): void
    {
        $this->approved();
        $this->generate();
        $payment = Payment::firstOrFail();

        $this->actingAs($this->officer)->from('/payments')
            ->post(route('payments.pay', $payment), $this->bankPayload(['method' => 'mfs', 'bank_name' => 'bKash', 'account_number' => '12345']))
            ->assertSessionHasErrors('account_number');

        $this->actingAs($this->officer)
            ->post(route('payments.pay', $payment), $this->bankPayload(['method' => 'mfs', 'bank_name' => 'bKash', 'account_number' => '01712345678']))
            ->assertSessionHasNoErrors();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    /** TC-PMT-008: Cash payment needs no reference */
    public function test_cash_payment_needs_no_reference(): void
    {
        $this->approved();
        $this->generate();
        $payment = Payment::firstOrFail();

        $this->actingAs($this->officer)->post(route('payments.pay', $payment), ['method' => 'cash', 'payment_date' => '2026-10-05'])
            ->assertSessionHasNoErrors();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    /** TC-PMT-009: The payment date cannot be in the future or before the payroll month */
    public function test_payment_date_limits(): void
    {
        $this->approved();
        $this->generate();
        $payment = Payment::firstOrFail();

        $this->actingAs($this->officer)->from('/payments')->post(route('payments.pay', $payment), $this->bankPayload(['payment_date' => '2026-10-20']))
            ->assertSessionHasErrors('payment_date');
        $this->actingAs($this->officer)->from('/payments')->post(route('payments.pay', $payment), $this->bankPayload(['payment_date' => '2026-08-31']))
            ->assertSessionHasErrors('payment_date');
    }

    /** TC-PMT-010: A paid payment cannot be paid, failed or cancelled again */
    public function test_paid_payment_is_final(): void
    {
        $this->approved();
        $this->generate();
        $payment = Payment::firstOrFail();
        $this->actingAs($this->officer)->post(route('payments.pay', $payment), $this->bankPayload());

        $this->actingAs($this->officer)->post(route('payments.pay', $payment), $this->bankPayload(['transaction_reference' => 'TRX-2']))->assertSessionHas('error');
        $this->actingAs($this->officer)->post(route('payments.fail', $payment), ['remarks' => 'oops'])->assertSessionHas('error');
        $this->actingAs($this->officer)->post(route('payments.cancel', $payment), ['remarks' => 'oops'])->assertSessionHas('error');

        $this->assertSame('TRX-1001', $payment->fresh()->transaction_reference);
    }

    /** TC-PMT-011: A failed payment can be recorded again later */
    public function test_failed_payment_can_be_retried(): void
    {
        $this->approved();
        $this->generate();
        $payment = Payment::firstOrFail();

        $this->actingAs($this->officer)->post(route('payments.fail', $payment), ['remarks' => 'Account closed'])->assertSessionHasNoErrors();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('approved', $this->period->fresh()->status);

        $this->actingAs($this->officer)->post(route('payments.pay', $payment), $this->bankPayload())->assertSessionHasNoErrors();
        $this->assertSame('paid', $payment->fresh()->status);
    }

    /** TC-PMT-012: A cancelled line is replaced when the list is prepared again */
    public function test_cancelled_line_is_replaced(): void
    {
        $this->approved();
        $this->generate();
        $payment = Payment::firstOrFail();

        $this->actingAs($this->officer)->post(route('payments.cancel', $payment), ['remarks' => 'Wrong account'])->assertSessionHasNoErrors();
        $this->generate();

        $this->assertSame(2, Payment::count());
        $this->assertSame(1, Payment::where('status', 'pending')->count());
    }

    /** TC-PMT-013: Several payments can be paid together with one batch reference, all or nothing */
    public function test_bulk_payment(): void
    {
        $second = $this->makeEmployee(['bank_name' => 'City Bank', 'bank_account_number' => '9876543210123']);
        $this->addToSalary($second, 'HRA');
        $this->addToSalary($second, 'MED');
        $this->approved();
        $this->generate();

        $ids = Payment::pluck('id')->all();
        $this->assertCount(2, $ids);

        // Without a batch reference bank payments are refused and nothing is paid.
        $this->actingAs($this->officer)->post(route('payments.bulk'), ['ids' => $ids, 'payment_date' => '2026-10-05'])->assertSessionHas('error');
        $this->assertSame(0, Payment::where('status', 'paid')->count());

        $this->actingAs($this->officer)->post(route('payments.bulk'), ['ids' => $ids, 'payment_date' => '2026-10-05', 'reference' => 'BATCH-77'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Payment::where('status', 'paid')->where('transaction_reference', 'BATCH-77')->count());
        $this->assertSame('paid', $this->period->fresh()->status);
    }

    /** TC-PMT-014: Payroll cannot be reopened once any salary has been paid */
    public function test_reopen_is_refused_after_a_payment(): void
    {
        $second = $this->makeEmployee();
        $this->addToSalary($second, 'HRA');
        $this->approved();
        $this->generate();

        $payment = Payment::where('employee_id', $this->employee->id)->firstOrFail();
        $this->actingAs($this->officer)->post(route('payments.pay', $payment), $this->bankPayload());
        $this->assertSame('approved', $this->period->fresh()->status);   // the second employee is still unpaid

        try {
            app(PayrollService::class)->reopen($this->period, $this->admin, 'Trying to change a paid month');
            $this->fail('Reopening should have been refused.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('already paid', $e->getMessage());
        }

        $this->assertSame('approved', $this->period->fresh()->status);
    }

    /** TC-PMT-015: Reopening an approved payroll removes the unpaid payment lines */
    public function test_reopen_removes_unpaid_payment_lines(): void
    {
        $this->approved();
        $this->generate();
        $this->assertSame(1, Payment::count());

        $this->actingAs($this->admin)->post(route('payroll.reopen', $this->period), ['reason' => 'Overtime was wrong'])->assertSessionHasNoErrors();

        $this->assertSame(0, Payment::count());
        $this->assertSame('draft', $this->period->fresh()->status);
    }

    /** TC-PMT-016: A paid period can be closed by the administrator only */
    public function test_closing_a_paid_period(): void
    {
        $this->approved();
        $this->generate();
        $this->actingAs($this->officer)->post(route('payments.pay', Payment::firstOrFail()), $this->bankPayload());

        $this->actingAs($this->officer)->post(route('payroll.close', $this->period))->assertForbidden();
        $this->actingAs($this->admin)->post(route('payroll.close', $this->period))->assertSessionHasNoErrors();

        $this->assertSame('closed', $this->period->fresh()->status);
    }
}
