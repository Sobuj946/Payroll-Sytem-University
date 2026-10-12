<?php

namespace Tests\Unit;

use App\Models\LeaveType;
use App\Services\LeaveService;
use Carbon\Carbon;
use Tests\TestCase;

class LeaveServiceTest extends TestCase
{
    private LeaveService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LeaveService::class);
    }

    /** TC-UNT-010: Leave days skip the weekly off day */
    public function test_days_skip_weekly_off(): void
    {
        // Monday 12 to Sunday 18 October 2026; Friday 16 is off.
        $this->assertSame(6.0, $this->service->daysBetween(Carbon::parse('2026-10-12'), Carbon::parse('2026-10-18')));
        $this->assertSame(0.0, $this->service->daysBetween(Carbon::parse('2026-10-16'), Carbon::parse('2026-10-16')));
    }

    /** TC-UNT-011: A half day counts 0.5 on a working day and nothing on a day off */
    public function test_half_day(): void
    {
        $this->assertSame(0.5, $this->service->daysBetween(Carbon::parse('2026-10-14'), Carbon::parse('2026-10-14'), true));
        $this->assertSame(0.0, $this->service->daysBetween(Carbon::parse('2026-10-16'), Carbon::parse('2026-10-16'), true));
    }

    /** TC-UNT-012: Holidays are not counted either */
    public function test_holidays_are_skipped(): void
    {
        // 16 December 2026 is Victory Day (Wednesday).
        $this->assertSame(2.0, $this->service->daysBetween(Carbon::parse('2026-12-15'), Carbon::parse('2026-12-17')));
    }

    /** TC-UNT-013: Only paid types with a yearly allowance are limited by a balance */
    public function test_which_types_are_limited(): void
    {
        $this->assertTrue($this->service->isLimited(LeaveType::where('name', 'Casual Leave')->first()));
        $this->assertFalse($this->service->isLimited(LeaveType::where('name', 'Unpaid Leave')->first()));
    }

    /** TC-UNT-014: A balance row is created from the leave type's allowance the first time it is needed */
    public function test_balance_is_created_on_demand(): void
    {
        $employee = $this->makeEmployee();
        $casual = LeaveType::where('name', 'Casual Leave')->first();

        $balance = $this->service->balance($employee, $casual, 2026);

        $this->assertSame(10.0, (float) $balance->allocated);
        $this->assertSame(0.0, (float) $balance->used);
        $this->assertSame(10.0, $this->service->available($employee, $casual, 2026));
    }
}
