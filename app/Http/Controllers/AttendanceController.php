<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\AttendanceService;
use App\Services\AuditService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance)
    {
    }

    /** Daily sheet: one row per employee for the chosen date. */
    public function index(Request $request)
    {
        $date = $this->parseDate($request->query('date'));

        $employees = Employee::with('department')
            ->where('status', 'active')
            ->whereDate('joining_date', '<=', $date->toDateString())
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->orderBy('employee_code')
            ->get();

        $records = Attendance::whereDate('date', $date->toDateString())
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()->keyBy('employee_id');

        $onLeave = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->pluck('employee_id')->all();

        return view('attendance.index', [
            'date' => $date,
            'employees' => $employees,
            'records' => $records,
            'onLeave' => $onLeave,
            'offReason' => $this->attendance->offReason($date),
            'canEdit' => auth()->user()->hasPermission('attendance.manage') && ! $date->isFuture(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Saves the whole daily sheet in one transaction. Re-saving a date updates the same rows. */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'rows.*.status' => ['required', Rule::in(Attendance::STATUSES)],
            'rows.*.check_in' => ['nullable', 'date_format:H:i'],
            'rows.*.check_out' => ['nullable', 'date_format:H:i'],
            'rows.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'date.before_or_equal' => 'Attendance cannot be recorded for a future date.',
            'rows.required' => 'There are no employees to save.',
            'rows.*.status.required' => 'Please choose a status for every employee.',
            'rows.*.check_in.date_format' => 'Enter the check-in time as HH:MM.',
            'rows.*.check_out.date_format' => 'Enter the check-out time as HH:MM.',
            'rows.*.remarks.max' => 'Remarks may not be longer than 255 characters.',
        ]);

        $validator->after(function ($v) use ($request) {
            foreach ((array) $request->input('rows', []) as $i => $row) {
                $in = $row['check_in'] ?? null;
                $out = $row['check_out'] ?? null;

                if ($in && $out && $out <= $in) {
                    $v->errors()->add("rows.$i.check_out", 'Check-out must be later than check-in.');
                }
            }
        });

        $data = $validator->validate();
        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();

        // Ignore anyone who had not joined yet on that date.
        $eligible = Employee::whereIn('id', collect($data['rows'])->pluck('employee_id'))
            ->whereDate('joining_date', '<=', $date->toDateString())
            ->pluck('id')->flip();

        $saved = 0;

        DB::transaction(function () use ($data, $date, $eligible, &$saved) {
            foreach ($data['rows'] as $row) {
                if (! isset($eligible[(int) $row['employee_id']])) {
                    continue;
                }

                $status = $row['status'];
                $in = $row['check_in'] ?? null;
                $out = $row['check_out'] ?? null;

                if (in_array($status, ['absent', 'leave'], true)) {
                    $in = $out = null;
                } else {
                    $status = $this->attendance->resolveStatus($status, $in);
                }

                Attendance::updateOrCreate(
                    ['employee_id' => $row['employee_id'], 'date' => $date->toDateString()],
                    [
                        'check_in' => $in,
                        'check_out' => $out,
                        'working_hours' => $this->attendance->workingHours($in, $out),
                        'status' => $status,
                        'remarks' => $row['remarks'] ?? null,
                    ]
                );
                $saved++;
            }
        });

        AuditService::log('saved', 'attendance', "Attendance for {$date->format('d M Y')} saved for {$saved} employees");

        return redirect()
            ->route('attendance.index', ['date' => $date->toDateString(), 'department_id' => $request->input('department_id')])
            ->with('success', "Attendance for {$date->format('d M Y')} has been saved ({$saved} employees).");
    }

    /** Month grid: employees down the side, days across the top. Also serves as the department view. */
    public function monthly(Request $request)
    {
        $month = $this->parseMonth($request->query('month'));
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $employees = Employee::with('department')
            ->where(function ($q) use ($start, $end) {
                $q->where('status', 'active')
                    ->orWhereHas('attendances', fn ($a) => $a->whereBetween('date', [$start->toDateString(), $end->toDateString()]));
            })
            ->whereDate('joining_date', '<=', $end->toDateString())
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->orderBy('employee_code')
            ->get();

        $records = Attendance::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($r) => $r->date->format('Y-m-d')));

        return view('attendance.monthly', [
            'month' => $month,
            'days' => collect(CarbonPeriod::create($start, $end)),
            'offDays' => $this->attendance->offDaysFor($start, $end),
            'employees' => $employees,
            'records' => $records,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Late and absent reports for a date range. */
    public function report(Request $request)
    {
        $type = $request->query('type') === 'absent' ? 'absent' : 'late';
        $from = $this->parseDate($request->query('from'), now()->startOfMonth());
        $to = $this->parseDate($request->query('to'), today());

        $base = Attendance::where('status', $type)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->department_id));
            });

        $summary = (clone $base)
            ->selectRaw('employee_id, COUNT(*) as total')
            ->groupBy('employee_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('employee')
            ->get();

        $rows = $base->with('employee.department')
            ->orderByDesc('date')->orderBy('employee_id')
            ->paginate(20)->withQueryString();

        return view('attendance.report', [
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'rows' => $rows,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** One employee's month, seen by HR / payroll. */
    public function employee(Request $request, Employee $employee)
    {
        return $this->history($request, $employee, false);
    }

    /** The signed-in employee's own month. */
    public function mine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return $this->history($request, $employee, true);
    }

    private function history(Request $request, Employee $employee, bool $self)
    {
        $month = $this->parseMonth($request->query('month'));
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $records = $employee->attendances()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        return view('attendance.employee', [
            'employee' => $employee->load('department', 'designation'),
            'self' => $self,
            'month' => $month,
            'days' => collect(CarbonPeriod::create($start, $end)),
            'offDays' => $this->attendance->offDaysFor($start, $end),
            'records' => $records,
            'totals' => $records->groupBy('status')->map->count(),
            'hours' => $records->sum('working_hours'),
        ]);
    }

    private function parseDate(?string $value, ?Carbon $default = null): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('!Y-m-d', $value) : ($default ?? today());
        } catch (Throwable) {
            return $default ?? today();
        }
    }

    private function parseMonth(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('!Y-m', $value)->startOfMonth() : now()->startOfMonth();
        } catch (Throwable) {
            return now()->startOfMonth();
        }
    }
}
