<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    private Employee $employee;
    private User $employeeUser;
    private LeaveType $casual;

    protected function setUp(): void
    {
        parent::setUp();

        // A Monday. The weekly off day is Friday (Settings), so 16 and 23 October are Fridays.
        $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));

        $this->employee = $this->makeEmployee();
        $this->employeeUser = $this->userWithRole('employee', $this->employee);
        $this->casual = LeaveType::where('name', 'Casual Leave')->firstOrFail();
    }

    private function askForLeave(array $overrides = [])
    {
        return $this->actingAs($this->employeeUser)->from(route('my.leave.create'))->post(route('my.leave.store'), array_merge([
            'leave_type_id' => $this->casual->id,
            'start_date' => '2026-10-14',
            'end_date' => '2026-10-15',
            'reason' => 'Family function',
        ], $overrides));
    }

    private function pendingRequest(): LeaveRequest
    {
        $this->askForLeave()->assertSessionHasNoErrors();

        return LeaveRequest::firstOrFail();
    }

    private function usedDays(): float
    {
        return (float) LeaveBalance::where(['employee_id' => $this->employee->id, 'leave_type_id' => $this->casual->id, 'year' => 2026])->value('used');
    }

    /** TC-LEV-001: Employee submits a leave request */
    public function test_employee_submits_leave_request(): void
    {
        $this->askForLeave()->assertSessionHasNoErrors();

        $leave = LeaveRequest::first();
        $this->assertSame('pending', $leave->status);
        $this->assertSame(2.0, (float) $leave->days);
        $this->assertSame($this->employee->id, $leave->employee_id);
    }

    /** TC-LEV-002: Weekly off days are not counted as leave days */
    public function test_weekly_off_days_are_not_counted(): void
    {
        // Wednesday to Saturday: Friday is the weekly off day, so 3 days.
        $this->askForLeave(['start_date' => '2026-10-14', 'end_date' => '2026-10-17'])->assertSessionHasNoErrors();

        $this->assertSame(3.0, (float) LeaveRequest::first()->days);
    }

    /** TC-LEV-003: A request that covers only a weekly off day is refused */
    public function test_only_a_weekly_off_day_is_refused(): void
    {
        $this->askForLeave(['start_date' => '2026-10-16', 'end_date' => '2026-10-16'])->assertSessionHasErrors('start_date');

        $this->assertSame(0, LeaveRequest::count());
    }

    /** TC-LEV-004: A request that overlaps another pending or approved request is refused */
    public function test_overlapping_request_is_refused(): void
    {
        $this->pendingRequest();

        $this->askForLeave(['start_date' => '2026-10-15', 'end_date' => '2026-10-18'])->assertSessionHasErrors('start_date');

        $this->assertSame(1, LeaveRequest::count());
    }

    /** TC-LEV-005: A request for more days than the balance is refused */
    public function test_request_above_balance_is_refused(): void
    {
        // 18 to 31 October is 12 working days (two Fridays are off); Casual Leave allows 10 a year.
        $this->askForLeave(['start_date' => '2026-10-18', 'end_date' => '2026-10-31'])->assertSessionHasErrors('leave_type_id');

        $this->assertSame(0, LeaveRequest::count());
    }

    /** TC-LEV-006: Days waiting for approval are held back from the balance */
    public function test_pending_days_count_against_the_balance(): void
    {
        $this->askForLeave(['start_date' => '2026-10-18', 'end_date' => '2026-10-24'])->assertSessionHasNoErrors(); // 6 working days (Friday is off)

        // 4 days remain free, so 5 more must be refused.
        $this->askForLeave(['start_date' => '2026-11-01', 'end_date' => '2026-11-05'])->assertSessionHasErrors('leave_type_id');
    }

    /** TC-LEV-007: Unpaid leave has no balance limit */
    public function test_unpaid_leave_is_not_limited(): void
    {
        $unpaid = LeaveType::where('name', 'Unpaid Leave')->firstOrFail();

        $this->askForLeave(['leave_type_id' => $unpaid->id, 'start_date' => '2026-10-18', 'end_date' => '2026-11-12'])->assertSessionHasNoErrors();

        $this->assertSame(1, LeaveRequest::count());
    }

    /** TC-LEV-008: Leave cannot start before the employee joined */
    public function test_leave_before_joining_date_is_refused(): void
    {
        $this->employee->update(['joining_date' => '2026-10-20']);

        $this->askForLeave(['start_date' => '2026-10-14', 'end_date' => '2026-10-15'])->assertSessionHasErrors('start_date');
    }

    /** TC-LEV-009: A half day counts as 0.5 */
    public function test_half_day_counts_as_half(): void
    {
        $this->askForLeave(['start_date' => '2026-10-14', 'end_date' => '2026-10-14', 'half_day' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(0.5, (float) LeaveRequest::first()->days);
    }

    /** TC-LEV-010: HR approves the request and the balance is reduced */
    public function test_approval_reduces_the_balance(): void
    {
        $leave = $this->pendingRequest();

        $this->actingAs($this->userWithRole('hr_manager'))->post(route('leave.approve', $leave))->assertSessionHasNoErrors();

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertNotNull($leave->approved_by);
        $this->assertSame(2.0, $this->usedDays());
        $this->assertDatabaseHas('audit_logs', ['action' => 'approved', 'module' => 'leave', 'record_id' => $leave->id]);
    }

    /** TC-LEV-011: A request already decided cannot be approved again */
    public function test_decided_request_cannot_be_approved_twice(): void
    {
        $leave = $this->pendingRequest();
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->post(route('leave.approve', $leave));
        $this->actingAs($hr)->post(route('leave.approve', $leave))->assertSessionHas('error');

        $this->assertSame(2.0, $this->usedDays());
    }

    /** TC-LEV-012: Nobody can decide their own leave request */
    public function test_own_request_cannot_be_approved(): void
    {
        $hrEmployee = $this->makeEmployee();
        $hr = $this->userWithRole('hr_manager', $hrEmployee);

        $this->actingAs($hr)->post(route('my.leave.store'), [
            'leave_type_id' => $this->casual->id, 'start_date' => '2026-10-14', 'end_date' => '2026-10-14', 'reason' => 'Personal work',
        ])->assertSessionHasNoErrors();

        $leave = LeaveRequest::firstOrFail();

        $this->actingAs($hr)->post(route('leave.approve', $leave))->assertSessionHas('error');
        $this->assertSame('pending', $leave->fresh()->status);
    }

    /** TC-LEV-013: Rejecting needs a reason, and the balance stays untouched */
    public function test_rejection_needs_a_reason(): void
    {
        $leave = $this->pendingRequest();
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->from('/leave')->post(route('leave.reject', $leave), ['remarks' => ''])->assertSessionHasErrors('remarks');
        $this->assertSame('pending', $leave->fresh()->status);

        $this->actingAs($hr)->post(route('leave.reject', $leave), ['remarks' => 'Project deadline that week']);
        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertSame(0.0, $this->usedDays());
    }

    /** TC-LEV-014: Cancelling an approved leave that has not started gives the days back */
    public function test_cancelling_approved_future_leave_restores_balance(): void
    {
        $leave = $this->pendingRequest();
        $this->actingAs($this->userWithRole('hr_manager'))->post(route('leave.approve', $leave));
        $this->assertSame(2.0, $this->usedDays());

        $this->actingAs($this->employeeUser)->post(route('my.leave.cancel', $leave))->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $leave->fresh()->status);
        $this->assertSame(0.0, $this->usedDays());
    }

    /** TC-LEV-015: Leave that has already started cannot be cancelled by the employee */
    public function test_started_leave_cannot_be_cancelled(): void
    {
        $leave = $this->pendingRequest();
        $this->actingAs($this->userWithRole('hr_manager'))->post(route('leave.approve', $leave));

        $this->travelTo(Carbon::parse('2026-10-14 09:00:00'));

        $this->actingAs($this->employeeUser)->post(route('my.leave.cancel', $leave))->assertSessionHas('error');
        $this->assertSame('approved', $leave->fresh()->status);
    }

    /** TC-LEV-016: An employee cannot cancel somebody else's request */
    public function test_cannot_cancel_another_employees_request(): void
    {
        $leave = $this->pendingRequest();
        $stranger = $this->userWithRole('employee', $this->makeEmployee());

        $this->actingAs($stranger)->post(route('my.leave.cancel', $leave))->assertForbidden();
        $this->assertSame('pending', $leave->fresh()->status);
    }

    /** TC-LEV-017: The payroll officer can see leave but cannot approve it */
    public function test_payroll_officer_cannot_approve_leave(): void
    {
        $leave = $this->pendingRequest();

        $this->actingAs($this->userWithRole('payroll_officer'))->post(route('leave.approve', $leave))->assertForbidden();
    }
}
