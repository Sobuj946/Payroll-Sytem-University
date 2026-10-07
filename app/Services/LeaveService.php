<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function __construct(private AttendanceService $attendance)
    {
    }

    /** Working days in the range: weekly offs and holidays (from Settings) are not counted. */
    public function daysBetween(Carbon $start, Carbon $end, bool $halfDay = false): float
    {
        if ($halfDay) {
            return $start->isSameDay($end) && $this->attendance->offReason($start) === null ? 0.5 : 0.0;
        }

        $off = $this->attendance->offDaysFor($start, $end);
        $days = 0;

        foreach (CarbonPeriod::create($start, $end) as $day) {
            if (! isset($off[$day->format('Y-m-d')])) {
                $days++;
            }
        }

        return (float) $days;
    }

    /** Only paid types with a yearly allowance are limited by a balance. Unpaid leave is not. */
    public function isLimited(LeaveType $type): bool
    {
        return $type->is_paid && $type->days_per_year > 0;
    }

    /** The employee's balance row for a year; created from the leave type's allowance on first use. */
    public function balance(Employee $employee, LeaveType $type, int $year): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
            ['allocated' => $type->days_per_year, 'used' => 0]
        );
    }

    /** Days still free to request: allocated - used - days waiting for approval. */
    public function available(Employee $employee, LeaveType $type, int $year, ?int $ignoreRequestId = null): float
    {
        $balance = $this->balance($employee, $type, $year);

        $pending = (float) LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('status', 'pending')
            ->whereYear('start_date', $year)
            ->when($ignoreRequestId, fn ($q) => $q->where('id', '!=', $ignoreRequestId))
            ->sum('days');

        return (float) $balance->allocated - (float) $balance->used - $pending;
    }

    public function hasOverlap(int $employeeId, Carbon $start, Carbon $end, ?int $ignoreId = null): bool
    {
        return LeaveRequest::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function approve(LeaveRequest $leaveRequest, User $by, ?string $remarks = null): void
    {
        DB::transaction(function () use ($leaveRequest, $by, $remarks) {
            $leave = $this->lockPending($leaveRequest, $by);
            $type = $leave->leaveType;

            if ($this->isLimited($type)) {
                $balance = $this->balance($leave->employee, $type, $leave->start_date->year);
                $balance = LeaveBalance::whereKey($balance->id)->lockForUpdate()->first();
                $left = (float) $balance->allocated - (float) $balance->used;

                if ($left < (float) $leave->days) {
                    throw new BusinessRuleException(
                        "{$leave->employee->full_name} has only " . ($left + 0) . " day(s) of {$type->name} left, so this request cannot be approved."
                    );
                }

                $balance->increment('used', $leave->days);
            }

            $leave->update([
                'status' => 'approved',
                'approved_by' => $by->id,
                'approved_at' => now(),
                'remarks' => $remarks,
            ]);

            AuditService::log(
                'approved', 'leave',
                "Leave request #{$leave->id} for {$leave->employee->full_name} ({$type->name}, " . ($leave->days + 0) . ' day(s)) was approved',
                $leave->id, $by->id
            );
        });
    }

    public function reject(LeaveRequest $leaveRequest, User $by, string $remarks): void
    {
        DB::transaction(function () use ($leaveRequest, $by, $remarks) {
            $leave = $this->lockPending($leaveRequest, $by);

            $leave->update([
                'status' => 'rejected',
                'approved_by' => $by->id,
                'approved_at' => now(),
                'remarks' => $remarks,
            ]);

            AuditService::log(
                'rejected', 'leave',
                "Leave request #{$leave->id} for {$leave->employee->full_name} was rejected",
                $leave->id, $by->id
            );
        });
    }

    /** The employee withdraws a pending request, or an approved one that has not started yet. */
    public function cancel(LeaveRequest $leaveRequest, User $by): void
    {
        DB::transaction(function () use ($leaveRequest, $by) {
            $leave = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->with('leaveType', 'employee')->firstOrFail();

            if (! in_array($leave->status, ['pending', 'approved'], true)) {
                throw new BusinessRuleException("This request is already {$leave->status} and cannot be cancelled.");
            }

            if ($leave->status === 'approved') {
                if ($leave->start_date->lte(today())) {
                    throw new BusinessRuleException('Leave that has already started cannot be cancelled here. Please contact HR.');
                }

                // Give the days back.
                if ($this->isLimited($leave->leaveType)) {
                    $balance = LeaveBalance::where([
                        'employee_id' => $leave->employee_id,
                        'leave_type_id' => $leave->leave_type_id,
                        'year' => $leave->start_date->year,
                    ])->lockForUpdate()->first();

                    if ($balance) {
                        $balance->update(['used' => max(0, (float) $balance->used - (float) $leave->days)]);
                    }
                }
            }

            $leave->update(['status' => 'cancelled']);

            AuditService::log(
                'cancelled', 'leave',
                "Leave request #{$leave->id} for {$leave->employee->full_name} was cancelled",
                $leave->id, $by->id
            );
        });
    }

    /** Locks the row and checks the request can still be decided and is not the approver's own. */
    private function lockPending(LeaveRequest $leaveRequest, User $by): LeaveRequest
    {
        $leave = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->with('leaveType', 'employee')->firstOrFail();

        if ($leave->status !== 'pending') {
            throw new BusinessRuleException("This request has already been {$leave->status}.");
        }

        if ($by->employee_id && $by->employee_id === $leave->employee_id) {
            throw new BusinessRuleException('You cannot decide your own leave request. Please ask another approver.');
        }

        return $leave;
    }
}
