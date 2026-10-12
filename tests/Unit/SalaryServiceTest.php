<?php

namespace Tests\Unit;

use App\Models\SalaryComponent;
use App\Services\SalaryService;
use Carbon\Carbon;
use Tests\TestCase;

class SalaryServiceTest extends TestCase
{
    private SalaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SalaryService::class);
    }

    /** TC-UNT-006: Percentage lines use the basic salary, fixed lines their amount */
    public function test_structure_amounts(): void
    {
        $employee = $this->makeEmployee(['basic_salary' => 50000]);
        $this->addToSalary($employee, 'HRA');          // 20% of basic
        $this->addToSalary($employee, 'MED');          // fixed 5,000
        $this->addToSalary($employee, 'TRN', 3500);    // own amount instead of the default

        $lines = $this->service->structure($employee)->keyBy('name');

        $this->assertMoney(10000, $lines['House Rent Allowance']['amount']);
        $this->assertMoney(5000, $lines['Medical Allowance']['amount']);
        $this->assertMoney(3500, $lines['Transport Allowance']['amount']);
        $this->assertSame('20% of basic', $lines['House Rent Allowance']['basis']);
    }

    /** TC-UNT-007: Totals add allowances and subtract deductions */
    public function test_totals(): void
    {
        $employee = $this->makeEmployee(['basic_salary' => 50000]);
        $this->addToSalary($employee, 'HRA');
        $this->addToSalary($employee, 'MED');
        $this->addToSalary($employee, 'PF');           // 5% of basic, a deduction

        $totals = $this->service->totals($employee);

        $this->assertMoney(15000, $totals['allowances']);
        $this->assertMoney(2500, $totals['deductions']);
        $this->assertMoney(65000, $totals['gross']);
        $this->assertMoney(62500, $totals['net']);
    }

    /** TC-UNT-008: A line that has ended, or not yet started, is not part of today's structure */
    public function test_dates_decide_which_lines_count(): void
    {
        $employee = $this->makeEmployee();
        $employee->salaryComponents()->create([
            'salary_component_id' => SalaryComponent::where('code', 'MED')->value('id'),
            'effective_from' => '2025-01-01', 'effective_to' => '2026-08-31',
        ]);
        $this->addToSalary($employee, 'TRN', 4000, '2026-10-01');

        $this->assertCount(1, $this->service->structure($employee, Carbon::parse('2026-08-15')));
        $this->assertCount(0, $this->service->structure($employee, Carbon::parse('2026-09-15')));
        $this->assertCount(1, $this->service->structure($employee, Carbon::parse('2026-10-15')));
    }

    /** TC-UNT-009: An inactive component is ignored in the totals */
    public function test_inactive_component_is_ignored(): void
    {
        $employee = $this->makeEmployee(['basic_salary' => 50000]);
        $this->addToSalary($employee, 'HRA');
        $this->addToSalary($employee, 'MED');

        SalaryComponent::where('code', 'HRA')->update(['status' => 'inactive']);

        $this->assertMoney(5000, $this->service->totals($employee)['allowances']);
    }
}
