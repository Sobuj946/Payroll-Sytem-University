<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use Tests\TestCase;

class PayslipTest extends TestCase
{
    private User $admin;
    private Employee $employee;
    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->admin, $this->employee, $this->period] = $this->payrollScenario();
    }

    private function payroll(): Payroll
    {
        return Payroll::where('employee_id', $this->employee->id)->firstOrFail();
    }

    /** TC-SLP-001: An employee opens their own salary slip */
    public function test_employee_opens_own_slip(): void
    {
        $this->approvePayroll($this->period, $this->admin);
        $worker = $this->userWithRole('employee', $this->employee);

        $this->actingAs($worker)->get(route('my.payslips'))->assertOk()->assertSee('September 2026');
        $this->actingAs($worker)->get(route('my.payslips.show', $this->payroll()))
            ->assertOk()
            ->assertSee('SALARY SLIP')
            ->assertSee('65,000.00')
            ->assertSee($this->employee->employee_code);
    }

    /** TC-SLP-002: An employee cannot open somebody else's slip */
    public function test_employee_cannot_open_another_slip(): void
    {
        $other = $this->makeEmployee();
        $this->addToSalary($other, 'HRA');
        $this->approvePayroll($this->period, $this->admin);

        $worker = $this->userWithRole('employee', $this->employee);
        $othersPayroll = Payroll::where('employee_id', $other->id)->firstOrFail();

        $this->actingAs($worker)->get(route('my.payslips.show', $othersPayroll))->assertForbidden();
        $this->actingAs($worker)->get(route('my.payslips.pdf', $othersPayroll))->assertForbidden();
        $this->actingAs($worker)->get(route('payslips.show', $othersPayroll))->assertForbidden();
    }

    /** TC-SLP-003: A slip does not exist before the payroll is approved */
    public function test_no_slip_for_unapproved_payroll(): void
    {
        $this->processPayroll($this->period, $this->admin);
        $worker = $this->userWithRole('employee', $this->employee);

        $this->actingAs($worker)->get(route('my.payslips.show', $this->payroll()))->assertNotFound();
        $this->actingAs($this->admin)->get(route('payslips.show', $this->payroll()))->assertNotFound();
        $this->actingAs($worker)->get(route('my.payslips'))->assertDontSee('September 2026');
    }

    /** TC-SLP-004: HR can view and print a slip but cannot download the PDF */
    public function test_hr_can_view_but_not_download(): void
    {
        $this->approvePayroll($this->period, $this->admin);
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->get(route('payslips.show', $this->payroll()))->assertOk();
        $this->actingAs($hr)->get(route('payslips.pdf', $this->payroll()))->assertForbidden();
        $this->actingAs($hr)->get(route('payslips.period.pdf', $this->period))->assertForbidden();
    }

    /** TC-SLP-005: The slip shows the company, the employee, every line and the bank account masked */
    public function test_slip_content(): void
    {
        $this->employee->update(['bank_name' => 'BRAC Bank', 'bank_account_number' => '1234567890123']);
        $this->approvePayroll($this->period, $this->admin);
        $officer = $this->userWithRole('payroll_officer');

        $this->actingAs($officer)->get(route('payslips.show', $this->payroll()))
            ->assertOk()
            ->assertSee('Padma Tech Solutions Ltd.')
            ->assertSee($this->employee->full_name)
            ->assertSee('Basic Salary')
            ->assertSee('House Rent Allowance')
            ->assertSee('Medical Allowance')
            ->assertSee('NET SALARY PAYABLE')
            ->assertSee('Not paid yet')
            ->assertSee('*********0123', false)
            ->assertDontSee('1234567890123');
    }

    /** TC-SLP-006: The payroll officer downloads a salary slip as PDF */
    public function test_pdf_download(): void
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $this->markTestSkipped('Install the PDF package first: composer require barryvdh/laravel-dompdf');
        }

        $this->approvePayroll($this->period, $this->admin);
        $officer = $this->userWithRole('payroll_officer');

        $response = $this->actingAs($officer)->get(route('payslips.pdf', $this->payroll()));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertDatabaseHas('audit_logs', ['module' => 'payslips', 'action' => 'downloaded']);
    }

    /** TC-SLP-007: The slip is a snapshot: changing the salary later does not change it */
    public function test_slip_is_a_snapshot(): void
    {
        $this->approvePayroll($this->period, $this->admin);
        $this->employee->update(['basic_salary' => 90000]);
        $this->employee->salaryComponents()->delete();

        $this->actingAs($this->userWithRole('payroll_officer'))->get(route('payslips.show', $this->payroll()))
            ->assertOk()->assertSee('65,000.00')->assertDontSee('90,000.00');
    }
}
