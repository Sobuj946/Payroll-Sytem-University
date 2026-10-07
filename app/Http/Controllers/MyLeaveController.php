<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\AuditService;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Leave pages for the signed-in employee. */
class MyLeaveController extends Controller
{
    public function __construct(private LeaveService $leave)
    {
    }

    public function index(Request $request)
    {
        $employee = $this->employee($request);
        $year = now()->year;

        $balances = LeaveType::active()->orderBy('name')->get()
            ->filter(fn ($type) => $this->leave->isLimited($type))
            ->map(function ($type) use ($employee, $year) {
                $row = $this->leave->balance($employee, $type, $year);

                return [
                    'type' => $type,
                    'allocated' => (float) $row->allocated,
                    'used' => (float) $row->used,
                    'pending' => (float) $row->allocated - (float) $row->used - $this->leave->available($employee, $type, $year),
                    'available' => $this->leave->available($employee, $type, $year),
                ];
            });

        return view('my-leave.index', [
            'employee' => $employee,
            'year' => $year,
            'balances' => $balances,
            'requests' => $employee->leaveRequests()->with('leaveType', 'approver')->orderByDesc('created_at')->paginate(10),
        ]);
    }

    public function create(Request $request)
    {
        $employee = $this->employee($request);
        $types = LeaveType::active()->orderBy('name')->get();

        // Days still available per limited type, shown next to the type name.
        $available = [];
        foreach ($types as $type) {
            if ($this->leave->isLimited($type)) {
                $available[$type->id] = $this->leave->available($employee, $type, now()->year);
            }
        }

        return view('my-leave.create', compact('types', 'available'));
    }

    public function store(StoreLeaveRequest $request)
    {
        $employee = $request->user()->employee;
        $data = $request->validated();

        $days = $this->leave->daysBetween(
            Carbon::createFromFormat('!Y-m-d', $data['start_date']),
            Carbon::createFromFormat('!Y-m-d', $data['end_date']),
            $request->boolean('half_day')
        );

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days' => $days,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        AuditService::log('requested', 'leave', "{$employee->full_name} requested " . ($days + 0) . ' day(s) of leave', $leave->id);

        return redirect()->route('my.leave')->with('success', 'Your leave request has been sent for approval.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        abort_unless($leaveRequest->employee_id === $request->user()->employee_id, 403);

        $this->leave->cancel($leaveRequest, $request->user());

        return redirect()->route('my.leave')->with('success', 'Your leave request has been cancelled.');
    }

    private function employee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return $employee;
    }
}
