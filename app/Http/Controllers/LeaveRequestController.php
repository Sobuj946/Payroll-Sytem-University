<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Illuminate\Http\Request;

/** Leave requests as seen by HR and admin. */
class LeaveRequestController extends Controller
{
    public function __construct(private LeaveService $leave)
    {
    }

    public function index(Request $request)
    {
        $requests = LeaveRequest::with('employee.department', 'leaveType')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('leave_type_id'), fn ($q) => $q->where('leave_type_id', $request->leave_type_id))
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->department_id));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . trim($request->q) . '%';
                $q->whereHas('employee', function ($e) use ($term) {
                    $e->whereRaw("CONCAT(first_name, ' ', last_name) like ?", [$term])->orWhere('employee_code', 'like', $term);
                });
            })
            ->orderByRaw("status = 'pending' desc")
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('leave.index', [
            'requests' => $requests,
            'pendingCount' => LeaveRequest::where('status', 'pending')->count(),
            'types' => LeaveType::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $this->leave->approve($leaveRequest, $request->user());

        return back()->with('success', 'The leave request has been approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $data = $request->validate(
            ['remarks' => ['required', 'string', 'max:255']],
            ['remarks.required' => 'Please write a short reason for rejecting the request.']
        );

        $this->leave->reject($leaveRequest, $request->user(), $data['remarks']);

        return back()->with('success', 'The leave request has been rejected.');
    }

    /** Remaining days per employee for each limited leave type. */
    public function balances(Request $request)
    {
        $year = (int) $request->query('year', now()->year);
        $year = ($year >= 2000 && $year <= 2100) ? $year : now()->year;

        $types = LeaveType::active()->orderBy('name')->get()->filter(fn ($t) => $this->leave->isLimited($t))->values();

        $employees = Employee::with('department')
            ->where('status', 'active')
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->orderBy('employee_code')
            ->get();

        $rows = LeaveBalance::where('year', $year)->whereIn('employee_id', $employees->pluck('id'))->get()
            ->keyBy(fn ($b) => $b->employee_id . '-' . $b->leave_type_id);

        return view('leave.balances', [
            'year' => $year,
            'types' => $types,
            'employees' => $employees,
            'rows' => $rows,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
