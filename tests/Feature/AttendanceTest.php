<?php

namespace Tests\Feature;

use App\Models\Attendance;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    private function sheet(string $date, array $rows): array
    {
        return ['date' => $date, 'rows' => $rows];
    }

    /** TC-ATT-001: Save the daily attendance sheet */
    public function test_daily_sheet_is_saved_with_hours(): void
    {
        $a = $this->makeEmployee();
        $b = $this->makeEmployee();

        $this->actingAs($this->userWithRole('hr_manager'))->post(route('attendance.store'), $this->sheet('2026-09-01', [
            ['employee_id' => $a->id, 'status' => 'present', 'check_in' => '09:00', 'check_out' => '17:30'],
            ['employee_id' => $b->id, 'status' => 'present', 'check_in' => '08:50', 'check_out' => '16:50'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, Attendance::count());
        $this->assertMoney(8.5, Attendance::where('employee_id', $a->id)->value('working_hours'));
        $this->assertMoney(8.0, Attendance::where('employee_id', $b->id)->value('working_hours'));
    }

    /** TC-ATT-002: Saving the same day twice updates the record and never duplicates it */
    public function test_saving_twice_does_not_duplicate(): void
    {
        $a = $this->makeEmployee();
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->post(route('attendance.store'), $this->sheet('2026-09-01', [
            ['employee_id' => $a->id, 'status' => 'present', 'check_in' => '09:00', 'check_out' => '17:00'],
        ]));
        $this->actingAs($hr)->post(route('attendance.store'), $this->sheet('2026-09-01', [
            ['employee_id' => $a->id, 'status' => 'absent'],
        ]));

        $this->assertSame(1, Attendance::count());
        $this->assertSame('absent', Attendance::first()->status);
    }

    /** TC-ATT-003: The database itself refuses a second record for the same employee and date */
    public function test_database_refuses_duplicate_attendance(): void
    {
        $a = $this->makeEmployee();
        $row = ['employee_id' => $a->id, 'date' => '2026-09-02', 'status' => 'present', 'working_hours' => 0];

        Attendance::create($row);

        $this->expectException(QueryException::class);
        Attendance::create($row);
    }

    /** TC-ATT-004: Arriving after office start plus the grace period is saved as Late */
    public function test_late_arrival_is_detected(): void
    {
        $onTime = $this->makeEmployee();
        $late = $this->makeEmployee();

        // Settings: office starts 09:00 with a 10 minute grace period.
        $this->actingAs($this->userWithRole('hr_manager'))->post(route('attendance.store'), $this->sheet('2026-09-01', [
            ['employee_id' => $onTime->id, 'status' => 'present', 'check_in' => '09:10', 'check_out' => '17:00'],
            ['employee_id' => $late->id, 'status' => 'present', 'check_in' => '09:11', 'check_out' => '17:00'],
        ]));

        $this->assertSame('present', Attendance::where('employee_id', $onTime->id)->value('status'));
        $this->assertSame('late', Attendance::where('employee_id', $late->id)->value('status'));
    }

    /** TC-ATT-005: Absent and Leave rows ignore any times entered */
    public function test_absent_rows_ignore_times(): void
    {
        $a = $this->makeEmployee();

        $this->actingAs($this->userWithRole('hr_manager'))->post(route('attendance.store'), $this->sheet('2026-09-01', [
            ['employee_id' => $a->id, 'status' => 'absent', 'check_in' => '09:00', 'check_out' => '17:00'],
        ]));

        $record = Attendance::first();
        $this->assertNull($record->check_in);
        $this->assertNull($record->check_out);
        $this->assertMoney(0, $record->working_hours);
    }

    /** TC-ATT-006: Check-out earlier than check-in is rejected */
    public function test_check_out_must_be_after_check_in(): void
    {
        $a = $this->makeEmployee();

        $this->actingAs($this->userWithRole('hr_manager'))->from('/attendance')
            ->post(route('attendance.store'), $this->sheet('2026-09-01', [
                ['employee_id' => $a->id, 'status' => 'present', 'check_in' => '17:00', 'check_out' => '09:00'],
            ]))->assertSessionHasErrors('rows.0.check_out');

        $this->assertSame(0, Attendance::count());
    }

    /** TC-ATT-007: Attendance cannot be recorded for a future date */
    public function test_future_date_is_rejected(): void
    {
        $a = $this->makeEmployee();

        $this->actingAs($this->userWithRole('hr_manager'))->from('/attendance')
            ->post(route('attendance.store'), $this->sheet(now()->addDay()->toDateString(), [
                ['employee_id' => $a->id, 'status' => 'present'],
            ]))->assertSessionHasErrors('date');
    }

    /** TC-ATT-008: An employee is not recorded for days before they joined */
    public function test_days_before_joining_are_ignored(): void
    {
        $newcomer = $this->makeEmployee(['joining_date' => '2026-09-15']);

        $this->actingAs($this->userWithRole('hr_manager'))->post(route('attendance.store'), $this->sheet('2026-09-01', [
            ['employee_id' => $newcomer->id, 'status' => 'present', 'check_in' => '09:00', 'check_out' => '17:00'],
        ]));

        $this->assertSame(0, Attendance::count());
    }

    /** TC-ATT-009: The payroll officer can look at attendance but not change it */
    public function test_payroll_officer_cannot_save_attendance(): void
    {
        $a = $this->makeEmployee();

        $this->actingAs($this->userWithRole('payroll_officer'))->post(route('attendance.store'), $this->sheet('2026-09-01', [
            ['employee_id' => $a->id, 'status' => 'present'],
        ]))->assertForbidden();
    }

    /** TC-ATT-010: An employee sees their own attendance only */
    public function test_employee_sees_only_own_attendance(): void
    {
        $mine = $this->makeEmployee(['first_name' => 'Mine']);
        $other = $this->makeEmployee(['first_name' => 'Other']);
        Attendance::create(['employee_id' => $mine->id, 'date' => now()->toDateString(), 'status' => 'present', 'check_in' => '09:00', 'check_out' => '17:00', 'working_hours' => 8]);
        Attendance::create(['employee_id' => $other->id, 'date' => now()->toDateString(), 'status' => 'absent', 'working_hours' => 0, 'remarks' => 'secret-remark']);

        $user = $this->userWithRole('employee', $mine);

        $this->actingAs($user)->get(route('my.attendance'))->assertOk()->assertDontSee('secret-remark');

        // The HR-style page for another employee is closed to them.
        $this->actingAs($user)->get(route('attendance.employee', $other))->assertForbidden();
    }
}
