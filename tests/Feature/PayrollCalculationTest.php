<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\Setting;
use App\Models\TaxSlab;
use Illuminate\Database\QueryException;
use Tests\TestCase;

/**
 * September 2026 has 30 days and 4 Fridays (the weekly off day), so 26 working days.
 * The employee earns a 50,000 basic + 10,000 House Rent (20%) + 5,000 Medical = 65,000,
 * so one day is worth 65,000 / 26 = 2,500.
 */
class PayrollCalculationTest extends TestCase
{
    private function absent(int $employeeId, string $date, string $status = 'absent'): void
    {
        Attendance::create(['employee_id' => $employeeId, 'date' => $date, 'status' => $status, 'working_hours' => 0]);
    }

    private function item(Payroll $payroll, string $startsWith): ?float
    {
        $amount = PayrollItem::where('payroll_id', $payroll->id)->where('name', 'like', $startsWith . '%')->value('amount');

        return $amount === null ? null : (float) $amount;
    }

    /** TC-PAY-001: Process monthly payroll */
    public function test_process_monthly_payroll(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();

        $summary = $this->processPayroll($period, $admin);

        $payroll = Payroll::firstOrFail();
        $this->assertSame(1, $summary['count']);
        $this->assertSame('processed', $period->fresh()->status);
        $this->assertMoney(50000, $payroll->basic_salary);
        $this->assertMoney(15000, $payroll->total_allowances);
        $this->assertMoney(65000, $payroll->gross_salary);
        $this->assertMoney(0, $payroll->total_deductions);
        $this->assertMoney(65000, $payroll->net_salary);
        $this->assertSame(26, (int) $payroll->working_days);
        $this->assertSame(3, $payroll->items()->count());
    }

    /** TC-PAY-002: Attempt duplicate payroll processing */
    public function test_processing_twice_does_not_duplicate(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();

        $this->processPayroll($period, $admin);
        $this->processPayroll($period, $admin);

        $this->assertSame(1, Payroll::count());
        $this->assertSame(3, PayrollItem::count());
    }

    /** TC-PAY-003: The database refuses a second payroll row for the same employee and period */
    public function test_database_refuses_duplicate_payroll_row(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $row = ['payroll_period_id' => $period->id, 'employee_id' => $employee->id, 'basic_salary' => 1, 'gross_salary' => 1, 'net_salary' => 1];

        Payroll::create($row);

        $this->expectException(QueryException::class);
        Payroll::create($row);
    }

    /** TC-PAY-004: Overtime and bonus are added to the gross salary */
    public function test_overtime_and_bonus(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        foreach (['OT' => 3000, 'BONUS' => 2000] as $code => $amount) {
            PayrollAdjustment::create([
                'payroll_period_id' => $period->id, 'employee_id' => $employee->id,
                'salary_component_id' => SalaryComponent::where('code', $code)->value('id'), 'amount' => $amount, 'created_by' => $admin->id,
            ]);
        }

        $this->processPayroll($period, $admin);

        $payroll = Payroll::firstOrFail();
        $this->assertMoney(3000, $payroll->overtime);
        $this->assertMoney(2000, $payroll->bonus);
        $this->assertMoney(70000, $payroll->gross_salary);
        $this->assertMoney(70000, $payroll->net_salary);
    }

    /** TC-PAY-005: Absent days are deducted at the daily rate */
    public function test_absence_deduction(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->absent($employee->id, '2026-09-01');
        $this->absent($employee->id, '2026-09-02');

        $this->processPayroll($period, $admin);

        $payroll = Payroll::firstOrFail();
        $this->assertMoney(2, $payroll->absent_days);
        $this->assertMoney(5000, $this->item($payroll, 'Absence Deduction'));
        $this->assertMoney(60000, $payroll->net_salary);
    }

    /** TC-PAY-006: A half day costs half a day's pay */
    public function test_half_day_deduction(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->absent($employee->id, '2026-09-01', 'half_day');

        $this->processPayroll($period, $admin);

        $this->assertMoney(1250, $this->item(Payroll::firstOrFail(), 'Absence Deduction'));
    }

    /** TC-PAY-007: Approved unpaid leave is deducted; approved paid leave is not */
    public function test_unpaid_leave_deduction(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $unpaid = LeaveType::where('name', 'Unpaid Leave')->firstOrFail();
        $casual = LeaveType::where('name', 'Casual Leave')->firstOrFail();

        $make = fn ($type, $from, $to, $days) => LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => $from, 'end_date' => $to,
            'days' => $days, 'reason' => 'Test leave', 'status' => 'approved',
        ]);
        $make($unpaid, '2026-09-07', '2026-09-09', 3);   // Monday to Wednesday
        $make($casual, '2026-09-14', '2026-09-15', 2);   // paid

        $this->processPayroll($period, $admin);

        $payroll = Payroll::firstOrFail();
        $this->assertMoney(3, $payroll->unpaid_leave_days);
        $this->assertMoney(7500, $this->item($payroll, 'Unpaid Leave Deduction'));
        $this->assertMoney(57500, $payroll->net_salary);
    }

    /** TC-PAY-008: A day covered by unpaid leave is not charged again as absent */
    public function test_no_double_charge_for_unpaid_leave_and_absence(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $unpaid = LeaveType::where('name', 'Unpaid Leave')->firstOrFail();
        LeaveRequest::create(['employee_id' => $employee->id, 'leave_type_id' => $unpaid->id, 'start_date' => '2026-09-07', 'end_date' => '2026-09-09', 'days' => 3, 'reason' => 'Test', 'status' => 'approved']);
        $this->absent($employee->id, '2026-09-08');

        $this->processPayroll($period, $admin);

        $payroll = Payroll::firstOrFail();
        $this->assertMoney(0, $payroll->absent_days);
        $this->assertMoney(57500, $payroll->net_salary);
    }

    /** TC-PAY-009: Late arrivals cost nothing unless the rule is switched on in Settings */
    public function test_late_rule_is_off_by_default(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
            $this->absent($employee->id, $date, 'late');
        }

        $this->processPayroll($period, $admin);

        $payroll = Payroll::firstOrFail();
        $this->assertSame(3, (int) $payroll->late_count);
        $this->assertMoney(65000, $payroll->net_salary);
    }

    /** TC-PAY-010: With "3 late arrivals cost one day" every 3 late arrivals cost one day */
    public function test_late_rule_when_switched_on(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        Setting::put('late_days_per_deduction', '3');
        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-06'] as $date) {
            $this->absent($employee->id, $date, 'late');
        }

        $this->processPayroll($period, $admin);

        $this->assertMoney(2500, $this->item(Payroll::firstOrFail(), 'Late Deduction'));
    }

    /** TC-PAY-011: Income tax comes from the saved slabs */
    public function test_income_tax_from_slabs(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        TaxSlab::create(['from_amount' => 0, 'to_amount' => 600000, 'rate_percent' => 0, 'effective_from' => '2026-01-01']);
        TaxSlab::create(['from_amount' => 600000, 'to_amount' => null, 'rate_percent' => 10, 'effective_from' => '2026-01-01']);

        $this->processPayroll($period, $admin);

        // 65,000 x 12 = 780,000. Tax = 10% of the 180,000 above 600,000 = 18,000 a year = 1,500 a month.
        $payroll = Payroll::firstOrFail();
        $this->assertMoney(1500, $payroll->tax);
        $this->assertMoney(63500, $payroll->net_salary);
    }

    /** TC-PAY-012: Without any tax slab no tax is deducted (nothing is invented) */
    public function test_no_slabs_means_no_tax(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();

        $this->processPayroll($period, $admin);

        $this->assertMoney(0, Payroll::firstOrFail()->tax);
    }

    /** TC-PAY-013: The daily rate follows the Settings: fixed days and basic-only */
    public function test_daily_rate_follows_settings(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->absent($employee->id, '2026-09-01');
        $this->absent($employee->id, '2026-09-02');

        Setting::put('working_day_basis', 'fixed');
        Setting::put('fixed_working_days', '30');
        $this->processPayroll($period, $admin);
        $this->assertMoney(4333.33, $this->item(Payroll::firstOrFail(), 'Absence Deduction'));    // 2 x 65,000 / 30

        Setting::put('working_day_basis', 'calendar_working_days');
        Setting::put('deduction_base', 'basic');
        $this->processPayroll($period, $admin);
        $this->assertMoney(3846.15, $this->item(Payroll::firstOrFail(), 'Absence Deduction'));    // 2 x 50,000 / 26
    }

    /** TC-PAY-014: A fixed provident fund deduction from the salary structure is applied */
    public function test_structure_deduction_is_applied(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->addToSalary($employee, 'PF');   // 5% of basic

        $this->processPayroll($period, $admin);

        $payroll = Payroll::firstOrFail();
        $this->assertMoney(2500, $payroll->total_deductions);
        $this->assertMoney(62500, $payroll->net_salary);
    }

    /** TC-PAY-015: A loan instalment entered for the month is deducted */
    public function test_monthly_deduction_item(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        PayrollAdjustment::create([
            'payroll_period_id' => $period->id, 'employee_id' => $employee->id,
            'salary_component_id' => SalaryComponent::where('code', 'LOAN')->value('id'), 'amount' => 4000, 'created_by' => $admin->id,
        ]);

        $this->processPayroll($period, $admin);

        $this->assertMoney(61000, Payroll::firstOrFail()->net_salary);
    }

    /** TC-PAY-016: Someone who joined in the middle of the month is paid for their days only */
    public function test_mid_month_joiner_is_prorated(): void
    {
        [$admin, , $period] = $this->payrollScenario();
        $joiner = $this->makeEmployee(['basic_salary' => 50000, 'joining_date' => '2026-09-16']);
        $this->addToSalary($joiner, 'HRA');
        $this->addToSalary($joiner, 'MED');

        $this->processPayroll($period, $admin);

        // Working days before the 16th: 15 days minus Fridays 4 and 11 = 13 days x 2,500.
        $payroll = Payroll::where('employee_id', $joiner->id)->firstOrFail();
        $this->assertMoney(32500, $this->item($payroll, 'Days before joining'));
        $this->assertMoney(32500, $payroll->net_salary);
    }

    /** TC-PAY-017: Someone who joins after the month ended, or is suspended or resigned, is not paid */
    public function test_ineligible_employees_are_skipped(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->makeEmployee(['status' => 'suspended']);
        $this->makeEmployee(['status' => 'resigned']);
        $this->makeEmployee(['status' => 'terminated']);
        $this->makeEmployee(['joining_date' => '2026-10-05']);

        $this->processPayroll($period, $admin);

        $this->assertSame(1, Payroll::count());
    }

    /** TC-PAY-018: The saved lines add up exactly to the totals */
    public function test_items_add_up_to_totals(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->addToSalary($employee, 'PF');
        $this->absent($employee->id, '2026-09-01');
        PayrollAdjustment::create(['payroll_period_id' => $period->id, 'employee_id' => $employee->id, 'salary_component_id' => SalaryComponent::where('code', 'OT')->value('id'), 'amount' => 1234.56, 'created_by' => $admin->id]);
        TaxSlab::create(['from_amount' => 0, 'to_amount' => 300000, 'rate_percent' => 0, 'effective_from' => '2026-01-01']);
        TaxSlab::create(['from_amount' => 300000, 'to_amount' => null, 'rate_percent' => 12.5, 'effective_from' => '2026-01-01']);

        $this->processPayroll($period, $admin);

        $payroll = Payroll::firstOrFail()->load('items');
        $this->assertMoney($payroll->items->where('type', 'earning')->sum('amount'), $payroll->gross_salary);
        $this->assertMoney($payroll->items->where('type', 'deduction')->sum('amount'), $payroll->total_deductions);
        $this->assertMoney($payroll->gross_salary - $payroll->total_deductions, $payroll->net_salary);
    }

    /** TC-PAY-019: If one employee fails, nothing is saved at all (transaction) */
    public function test_failure_rolls_everything_back(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $broke = $this->makeEmployee(['basic_salary' => 50000]);
        PayrollAdjustment::create([
            'payroll_period_id' => $period->id, 'employee_id' => $broke->id,
            'salary_component_id' => SalaryComponent::where('code', 'LOAN')->value('id'), 'amount' => 500000, 'created_by' => $admin->id,
        ]);

        try {
            $this->processPayroll($period, $admin);
            $this->fail('Processing should have stopped because of a negative net salary.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('negative', $e->getMessage());
        }

        $this->assertSame(0, Payroll::count());
        $this->assertSame(0, PayrollItem::count());
        $this->assertSame('draft', $period->fresh()->status);
    }

    /** TC-PAY-020: Processing with no eligible employee is refused */
    public function test_no_employees_means_no_payroll(): void
    {
        $admin = $this->userWithRole('admin');
        $period = $this->makePeriod($admin);

        $this->expectException(BusinessRuleException::class);
        $this->processPayroll($period, $admin);
    }

    /** TC-PAY-021: Reprocessing replaces the old result with the new one */
    public function test_reprocessing_uses_new_data(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->processPayroll($period, $admin);
        $this->assertMoney(65000, Payroll::firstOrFail()->net_salary);

        $this->absent($employee->id, '2026-09-01');
        $this->processPayroll($period, $admin);

        $this->assertSame(1, Payroll::count());
        $this->assertMoney(62500, Payroll::firstOrFail()->net_salary);
    }
}
