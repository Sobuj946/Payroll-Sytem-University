<?php

namespace Tests\Feature;

use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryComponent;
use Tests\TestCase;

class SalaryTest extends TestCase
{
    private function componentId(string $code): int
    {
        return SalaryComponent::where('code', $code)->value('id');
    }

    /** TC-SAL-001: Add an allowance to an employee's salary */
    public function test_add_allowance_to_salary(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($this->userWithRole('payroll_officer'))->post(route('salary.assign', $employee), [
            'salary_component_id' => $this->componentId('FOOD'),
            'value' => 2500,
            'effective_from' => '2026-09-01',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employee_salary_components', ['employee_id' => $employee->id, 'salary_component_id' => $this->componentId('FOOD')]);
    }

    /** TC-SAL-002: The same component cannot be added twice */
    public function test_same_component_cannot_be_added_twice(): void
    {
        $employee = $this->makeEmployee();
        $this->addToSalary($employee, 'FOOD');

        $this->actingAs($this->userWithRole('payroll_officer'))->post(route('salary.assign', $employee), [
            'salary_component_id' => $this->componentId('FOOD'),
            'effective_from' => '2026-09-01',
        ])->assertSessionHas('error');

        $this->assertSame(1, $employee->salaryComponents()->count());
    }

    /** TC-SAL-003: A percentage above 100 is refused */
    public function test_percentage_above_100_is_refused(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($this->userWithRole('payroll_officer'))->post(route('salary.assign', $employee), [
            'salary_component_id' => $this->componentId('HRA'),
            'value' => 150,
            'effective_from' => '2026-09-01',
        ])->assertSessionHas('error');

        $this->assertSame(0, $employee->salaryComponents()->count());
    }

    /** TC-SAL-004: Changing an amount keeps the history: the old line ends, a new one starts */
    public function test_changing_an_amount_keeps_history(): void
    {
        $employee = $this->makeEmployee();
        $old = $this->addToSalary($employee, 'HRA');

        $this->actingAs($this->userWithRole('payroll_officer'))->post(route('salary.revise', [$employee, $old]), [
            'value' => 25,
            'effective_from' => '2026-09-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-08-31', $old->fresh()->effective_to->format('Y-m-d'));

        $new = EmployeeSalaryComponent::where('employee_id', $employee->id)->whereNull('effective_to')->firstOrFail();
        $this->assertMoney(25, $new->value);
        $this->assertSame('2026-09-01', $new->effective_from->format('Y-m-d'));
    }

    /** TC-SAL-005: A new amount cannot start before the line it replaces */
    public function test_new_amount_must_start_after_the_old_one(): void
    {
        $employee = $this->makeEmployee();
        $line = $this->addToSalary($employee, 'HRA', null, '2026-09-01');

        $this->actingAs($this->userWithRole('payroll_officer'))->post(route('salary.revise', [$employee, $line]), [
            'value' => 30,
            'effective_from' => '2026-08-01',
        ])->assertSessionHas('error');

        $this->assertNull($line->fresh()->effective_to);
    }

    /** TC-SAL-006: Revising the basic salary needs a reason and is recorded */
    public function test_basic_salary_revision_needs_a_reason(): void
    {
        $employee = $this->makeEmployee(['basic_salary' => 50000]);
        $officer = $this->userWithRole('payroll_officer');

        $this->actingAs($officer)->from('/salary')->post(route('salary.basic', $employee), ['basic_salary' => 55000, 'reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertMoney(50000, $employee->fresh()->basic_salary);

        $this->actingAs($officer)->post(route('salary.basic', $employee), ['basic_salary' => 55000, 'reason' => 'Annual increment'])
            ->assertSessionHasNoErrors();

        $this->assertMoney(55000, $employee->fresh()->basic_salary);
        $this->assertDatabaseHas('audit_logs', ['action' => 'salary_changed', 'record_id' => $employee->id]);
    }

    /** TC-SAL-007: HR can see a salary structure but cannot change it */
    public function test_hr_cannot_change_salary(): void
    {
        $employee = $this->makeEmployee(['basic_salary' => 50000]);
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->get(route('salary.employee', $employee))->assertOk();

        $this->actingAs($hr)->post(route('salary.assign', $employee), [
            'salary_component_id' => $this->componentId('FOOD'),
            'effective_from' => '2026-09-01',
        ])->assertForbidden();

        $this->actingAs($hr)->post(route('salary.basic', $employee), ['basic_salary' => 99999, 'reason' => 'Because'])->assertForbidden();
        $this->assertMoney(50000, $employee->fresh()->basic_salary);
    }

    /** TC-SAL-008: Components calculated by the payroll engine cannot be edited */
    public function test_system_components_are_locked(): void
    {
        $tax = SalaryComponent::where('code', 'TAX')->firstOrFail();

        $this->actingAs($this->userWithRole('admin'))->get(route('salary.components.edit', $tax))->assertSessionHas('error');
        $this->actingAs($this->userWithRole('admin'))->patch(route('salary.components.status', $tax))->assertSessionHas('error');

        $this->assertSame('active', $tax->fresh()->status);
    }

    /** TC-SAL-009: A new component needs a unique capital-letter code */
    public function test_new_component_code_rules(): void
    {
        $admin = $this->userWithRole('admin');
        $payload = ['name' => 'Conveyance', 'code' => 'CONV', 'type' => 'earning', 'source' => 'structure', 'calc_type' => 'fixed', 'default_value' => 1500, 'is_taxable' => 1, 'status' => 'active'];

        $this->actingAs($admin)->post(route('salary.components.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('salary_components', ['code' => 'CONV']);

        $this->actingAs($admin)->from('/salary/components/create')->post(route('salary.components.store'), $payload)->assertSessionHasErrors('code');
        $this->actingAs($admin)->from('/salary/components/create')->post(route('salary.components.store'), array_merge($payload, ['code' => 'bad code']))->assertSessionHasErrors('code');
    }
}
