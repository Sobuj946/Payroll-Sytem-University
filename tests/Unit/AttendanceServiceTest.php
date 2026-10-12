<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AttendanceService::class);
    }

    /** TC-UNT-001: Working hours are the time between check-in and check-out */
    public function test_working_hours(): void
    {
        $this->assertSame(8.5, $this->service->workingHours('09:00', '17:30'));
        $this->assertSame(7.75, $this->service->workingHours('09:45', '17:30'));
    }

    /** TC-UNT-002: Missing or reversed times give zero hours */
    public function test_working_hours_edge_cases(): void
    {
        $this->assertSame(0.0, $this->service->workingHours(null, '17:00'));
        $this->assertSame(0.0, $this->service->workingHours('09:00', null));
        $this->assertSame(0.0, $this->service->workingHours('17:00', '09:00'));
        $this->assertSame(0.0, $this->service->workingHours('09:00', '09:00'));
    }

    /** TC-UNT-003: Late is decided by office start time plus grace, both from Settings */
    public function test_late_status_follows_settings(): void
    {
        $this->assertSame('present', $this->service->resolveStatus('present', '09:10'));
        $this->assertSame('late', $this->service->resolveStatus('present', '09:11'));

        Setting::put('office_start_time', '08:00');
        Setting::put('late_grace_minutes', '0');

        $this->assertSame('present', $this->service->resolveStatus('present', '08:00'));
        $this->assertSame('late', $this->service->resolveStatus('present', '08:01'));
    }

    /** TC-UNT-004: Other statuses and a missing check-in are left alone */
    public function test_other_statuses_are_unchanged(): void
    {
        $this->assertSame('absent', $this->service->resolveStatus('absent', '11:00'));
        $this->assertSame('half_day', $this->service->resolveStatus('half_day', '11:00'));
        $this->assertSame('present', $this->service->resolveStatus('present', null));
    }

    /** TC-UNT-005: Weekly offs come from Settings and holidays from the holiday table */
    public function test_off_days(): void
    {
        $off = $this->service->offDaysFor(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
        $this->assertCount(4, $off);
        $this->assertSame(['2026-09-04', '2026-09-11', '2026-09-18', '2026-09-25'], array_keys($off));

        Setting::put('weekly_off_days', '5,6');
        $this->assertCount(8, $this->service->offDaysFor(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')));

        $this->assertSame('Victory Day', $this->service->offReason(Carbon::parse('2026-12-16')));
        $this->assertNull($this->service->offReason(Carbon::parse('2026-09-02')));
    }
}
