<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\ReportService;
use Tests\TestCase;

class ReportFiltersTest extends TestCase
{
    /** TC-UNT-015: Bad filter values fall back to safe defaults */
    public function test_bad_values_fall_back(): void
    {
        $filters = app(ReportService::class)->filters('attendance.monthly', ['month' => 'garbage', 'department_id' => '']);

        $this->assertSame(now()->format('Y-m'), $filters['month']);
        $this->assertNull($filters['department_id']);
    }

    /** TC-UNT-016: A year is kept between 2000 and 2100 and a date must be real */
    public function test_year_and_date_limits(): void
    {
        $service = app(ReportService::class);

        $this->assertSame(2000, $service->filters('payroll.expense', ['year' => 1850])['year']);
        $this->assertSame(2100, $service->filters('payroll.expense', ['year' => 99999])['year']);
        $this->assertSame(today()->toDateString(), $service->filters('attendance.daily', ['date' => 'not-a-date'])['date']);
        $this->assertSame('2026-09-15', $service->filters('attendance.daily', ['date' => '2026-09-15'])['date']);
    }

    /** TC-UNT-017: Only known statuses and employment types are accepted */
    public function test_only_known_choices_are_accepted(): void
    {
        $filters = app(ReportService::class)->filters('employees.all', ['status' => 'hacked', 'employment_type' => 'volunteer']);

        $this->assertNull($filters['status']);
        $this->assertNull($filters['employment_type']);

        $filters = app(ReportService::class)->filters('employees.all', ['status' => 'resigned', 'employment_type' => 'contract']);
        $this->assertSame('resigned', $filters['status']);
        $this->assertSame('contract', $filters['employment_type']);
    }

    /** TC-UNT-018: Every report in the catalogue has a permission, a group and a title */
    public function test_catalogue_is_complete(): void
    {
        foreach (app(ReportService::class)->definitions() as $key => $definition) {
            foreach (['title', 'group', 'permission', 'filters', 'about'] as $field) {
                $this->assertArrayHasKey($field, $definition, "{$key} is missing {$field}");
            }
            $this->assertStringStartsWith('reports.', $definition['permission']);
        }
    }
}
