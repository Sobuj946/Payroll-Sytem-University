<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use Tests\TestCase;

class PayrollWorkflowTest extends TestCase
{
    /** TC-PAY-022: The workflow runs Draft, Processed, Reviewed, Approved with the right people */
    public function test_full_workflow_with_the_right_roles(): void
    {
        [$admin, , $period] = $this->payrollScenario();
        $officer = $this->userWithRole('payroll_officer');

        $this->actingAs($officer)->post(route('payroll.process', $period))->assertSessionHasNoErrors();
        $this->assertSame('processed', $period->fresh()->status);

        $this->actingAs($officer)->post(route('payroll.review', $period))->assertSessionHasNoErrors();
        $this->assertSame('reviewed', $period->fresh()->status);

        $this->actingAs($admin)->post(route('payroll.approve', $period))->assertSessionHasNoErrors();
        $period->refresh();
        $this->assertSame('approved', $period->status);
        $this->assertSame($admin->id, $period->approved_by);
        $this->assertNotNull($period->approved_at);
    }

    /** TC-PAY-023: A step cannot be skipped */
    public function test_steps_cannot_be_skipped(): void
    {
        [$admin, , $period] = $this->payrollScenario();

        // Draft cannot be reviewed or approved.
        $this->actingAs($admin)->post(route('payroll.review', $period))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('payroll.approve', $period))->assertSessionHas('error');
        $this->assertSame('draft', $period->fresh()->status);

        // Processed cannot be approved before it is reviewed.
        $this->processPayroll($period, $admin);
        $this->actingAs($admin)->post(route('payroll.approve', $period))->assertSessionHas('error');
        $this->assertSame('processed', $period->fresh()->status);
    }

    /** TC-PAY-024: Approved payroll cannot be processed again */
    public function test_approved_payroll_is_locked(): void
    {
        [$admin, , $period] = $this->payrollScenario();
        $this->approvePayroll($period, $admin);
        $net = Payroll::firstOrFail()->net_salary;

        $this->actingAs($admin)->post(route('payroll.process', $period))->assertSessionHas('error');

        $this->assertSame('approved', $period->fresh()->status);
        $this->assertSame($net, Payroll::firstOrFail()->net_salary);
    }

    /** TC-PAY-025: Items cannot be added once payroll is reviewed */
    public function test_items_are_locked_after_review(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->processPayroll($period, $admin);
        $this->actingAs($admin)->post(route('payroll.review', $period));

        $this->actingAs($admin)->post(route('salary.adjustments.store'), [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'salary_component_id' => SalaryComponent::where('code', 'BONUS')->value('id'),
            'amount' => 1000,
        ])->assertSessionHas('error');

        $this->assertSame(0, PayrollAdjustment::count());
    }

    /** TC-PAY-026: Items can still be changed while payroll is only processed, then it is processed again */
    public function test_items_can_change_while_processed(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->processPayroll($period, $admin);

        $this->actingAs($admin)->post(route('salary.adjustments.store'), [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'salary_component_id' => SalaryComponent::where('code', 'BONUS')->value('id'),
            'amount' => 1000,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, PayrollAdjustment::count());

        $this->actingAs($admin)->post(route('payroll.process', $period));
        $this->assertSame(66000.0, (float) Payroll::firstOrFail()->net_salary);
    }

    /** TC-PAY-027: Reopening needs a reason, goes back to Draft and is recorded with the old total */
    public function test_reopen_for_correction(): void
    {
        [$admin, , $period] = $this->payrollScenario();
        $this->approvePayroll($period, $admin);

        $this->actingAs($admin)->from('/payroll')->post(route('payroll.reopen', $period), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('approved', $period->fresh()->status);

        $this->actingAs($admin)->post(route('payroll.reopen', $period), ['reason' => 'Wrong overtime entered'])->assertSessionHasNoErrors();

        $period->refresh();
        $this->assertSame('draft', $period->status);
        $this->assertSame('Wrong overtime entered', $period->notes);
        $this->assertNull($period->approved_by);

        $log = AuditLog::where('action', 'reopened')->firstOrFail();
        $this->assertStringContainsString('Wrong overtime entered', $log->description);
        $this->assertStringContainsString('65,000.00', $log->description);
    }

    /** TC-PAY-028: A payroll period month can be created once only */
    public function test_period_is_unique_per_month(): void
    {
        $officer = $this->userWithRole('payroll_officer');
        $month = now()->format('Y-m');

        $this->actingAs($officer)->post(route('payroll.periods.store'), ['month' => $month])->assertSessionHasNoErrors();
        $this->actingAs($officer)->post(route('payroll.periods.store'), ['month' => $month]);

        $this->assertSame(1, PayrollPeriod::count());
        $this->assertSame('draft', PayrollPeriod::first()->status);
    }

    /** TC-PAY-029: A period too far in the future or the past is refused */
    public function test_period_month_limits(): void
    {
        $officer = $this->userWithRole('payroll_officer');

        $this->actingAs($officer)->from('/payroll')->post(route('payroll.periods.store'), ['month' => now()->addMonths(3)->format('Y-m')])->assertSessionHasErrors('month');
        $this->actingAs($officer)->from('/payroll')->post(route('payroll.periods.store'), ['month' => now()->subMonths(30)->format('Y-m')])->assertSessionHasErrors('month');

        $this->assertSame(0, PayrollPeriod::count());
    }

    /** TC-PAY-030: Every workflow step is written to the audit log */
    public function test_workflow_is_audited(): void
    {
        [$admin, , $period] = $this->payrollScenario();
        $this->approvePayroll($period, $admin);

        foreach (['processed', 'reviewed', 'approved'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['module' => 'payroll', 'action' => $action, 'record_id' => $period->id]);
        }
    }

    /** TC-PAY-031: The payroll officer and HR can view the figures; an employee cannot */
    public function test_who_can_view_payroll_pages(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->processPayroll($period, $admin);
        $payroll = Payroll::firstOrFail();

        foreach (['payroll_officer', 'hr_manager'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('payroll.show', $period))->assertOk();
            $this->actingAs($this->userWithRole($role))->get(route('payroll.record', $payroll))->assertOk();
        }

        $worker = $this->userWithRole('employee', $employee);
        $this->actingAs($worker)->get(route('payroll.show', $period))->assertForbidden();
        $this->actingAs($worker)->get(route('payroll.record', $payroll))->assertForbidden();
    }
}
