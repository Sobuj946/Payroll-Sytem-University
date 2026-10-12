<?php

namespace Tests\Feature;

use App\Models\Payroll;
use App\Services\ReportService;
use Tests\TestCase;

class ReportTest extends TestCase
{
    /** TC-RPT-001: HR opens employee reports but not payroll reports */
    public function test_hr_report_access(): void
    {
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->get(route('reports.show', 'employees.all'))->assertOk();
        $this->actingAs($hr)->get(route('reports.show', 'attendance.daily'))->assertOk();
        $this->actingAs($hr)->get(route('reports.show', 'leave.history'))->assertOk();
        $this->actingAs($hr)->get(route('reports.show', 'payroll.monthly'))->assertForbidden();
        $this->actingAs($hr)->get(route('reports.csv', 'payroll.monthly'))->assertForbidden();
    }

    /** TC-RPT-002: The payroll officer opens payroll reports but not employee reports */
    public function test_payroll_officer_report_access(): void
    {
        $officer = $this->userWithRole('payroll_officer');

        $this->actingAs($officer)->get(route('reports.show', 'payroll.monthly'))->assertOk();
        $this->actingAs($officer)->get(route('reports.show', 'employees.all'))->assertForbidden();
    }

    /** TC-RPT-003: An unknown report is a 404 */
    public function test_unknown_report(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/reports/does.not.exist')->assertNotFound();
    }

    /** TC-RPT-004: Every report in the catalogue opens for the administrator */
    public function test_every_report_opens(): void
    {
        $admin = $this->userWithRole('admin');
        $this->makeEmployee();

        foreach (array_keys(app(ReportService::class)->definitions()) as $key) {
            $status = $this->actingAs($admin)->get(route('reports.show', $key))->getStatusCode();
            $this->assertSame(200, $status, "report {$key}");
        }
    }

    /** TC-RPT-005: CSV export has a title, headers, the data and a byte order mark for Excel */
    public function test_csv_export(): void
    {
        $this->makeEmployee(['first_name' => 'Nusrat', 'last_name' => 'Jahan']);

        $response = $this->actingAs($this->userWithRole('hr_manager'))->get(route('reports.csv', 'employees.all'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('All employees', $csv);
        $this->assertStringContainsString('Employee ID', $csv);
        $this->assertStringContainsString('Nusrat Jahan', $csv);
    }

    /** TC-RPT-006: A cell that starts with = is neutralised so Excel cannot run it */
    public function test_csv_formula_injection_is_blocked(): void
    {
        $this->makeEmployee(['first_name' => '=HYPERLINK("http://evil.test")', 'last_name' => 'Smith']);

        $csv = $this->actingAs($this->userWithRole('hr_manager'))->get(route('reports.csv', 'employees.all'))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    /** TC-RPT-007: Filters work and bad filter values fall back safely */
    public function test_filters(): void
    {
        $this->makeEmployee(['first_name' => 'Active', 'last_name' => 'Person']);
        $this->makeEmployee(['first_name' => 'Gone', 'last_name' => 'Person', 'status' => 'resigned']);
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->get(route('reports.show', ['key' => 'employees.all', 'status' => 'resigned']))
            ->assertOk()->assertSee('Gone Person')->assertDontSee('Active Person');

        $this->actingAs($hr)->get(route('reports.show', ['key' => 'employees.active']))
            ->assertOk()->assertSee('Active Person')->assertDontSee('Gone Person');

        $this->actingAs($hr)->get(route('reports.show', ['key' => 'attendance.daily', 'date' => 'not-a-date', 'department_id' => '']))->assertOk();
    }

    /** TC-RPT-008: The monthly payroll report agrees with the payroll itself */
    public function test_payroll_report_matches_payroll(): void
    {
        [$admin, , $period] = $this->payrollScenario();
        $second = $this->makeEmployee(['basic_salary' => 30000]);
        $this->addToSalary($second, 'HRA');
        $this->processPayroll($period, $admin);

        $report = app(ReportService::class)->run('payroll.monthly', ['period' => $period->id]);

        $this->assertCount(2, $report['rows']);
        $this->assertMoney(Payroll::sum('gross_salary'), $report['totals']['gross']);
        $this->assertMoney(Payroll::sum('net_salary'), $report['totals']['net']);
        $this->assertMoney(Payroll::sum('total_deductions'), $report['totals']['deductions']);
    }

    /** TC-RPT-009: Department and expense reports add up to the same total */
    public function test_payroll_reports_agree_with_each_other(): void
    {
        [$admin, , $period] = $this->payrollScenario();
        $this->processPayroll($period, $admin);
        $service = app(ReportService::class);

        $monthly = $service->run('payroll.monthly', ['period' => $period->id]);
        $department = $service->run('payroll.department', ['period' => $period->id]);
        $expense = $service->run('payroll.expense', ['year' => 2026]);
        $deductions = $service->run('payroll.deductions', ['period' => $period->id]);

        $this->assertMoney($monthly['totals']['net'], $department['totals']['net']);
        $this->assertMoney($monthly['totals']['net'], $expense['totals']['net']);
        $this->assertMoney($monthly['totals']['deductions'], $deductions['totals']['total']);
    }

    /** TC-RPT-010: Paid and pending payroll reports split the salaries correctly */
    public function test_paid_and_pending_reports(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $this->approvePayroll($period, $admin);
        $service = app(ReportService::class);

        $pending = $service->run('payroll.pending', ['period' => $period->id]);
        $paid = $service->run('payroll.paid', ['period' => $period->id]);

        $this->assertCount(1, $pending['rows']);
        $this->assertCount(0, $paid['rows']);
        $this->assertSame('Not prepared', $pending['rows'][0]['payment']);
    }

    /** TC-RPT-011: Report exports are written to the audit log */
    public function test_export_is_audited(): void
    {
        $this->actingAs($this->userWithRole('hr_manager'))->get(route('reports.csv', 'employees.all'))->streamedContent();

        $this->assertDatabaseHas('audit_logs', ['module' => 'reports', 'action' => 'exported']);
    }
}
