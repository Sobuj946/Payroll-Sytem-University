<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Throwable;

/**
 * Every report is one entry in definitions() and one builder method.
 * A builder returns: columns, rows (raw values), totals (or null) and notes.
 * The screen, print, PDF and CSV versions all use that same result, so they always agree.
 *
 * Column types: text, number, money, date (Y-m-d), status.
 */
class ReportService
{
    private ?array $optionCache = null;

    // ------------------------------------------------------------------
    //  Catalogue
    // ------------------------------------------------------------------

    public function definitions(): array
    {
        $employee = 'reports.employee';
        $attendance = 'reports.attendance';
        $leave = 'reports.leave';
        $payroll = 'reports.payroll';

        return [
            'employees.all' => ['title' => 'All employees', 'group' => 'Employees', 'permission' => $employee, 'filters' => ['department_id', 'designation_id', 'employment_type', 'status'], 'about' => 'Every employee with department, designation and status.'],
            'employees.active' => ['title' => 'Active employees', 'group' => 'Employees', 'permission' => $employee, 'filters' => ['department_id', 'designation_id', 'employment_type'], 'about' => 'Employees who are currently active.'],
            'employees.department' => ['title' => 'Department-wise employees', 'group' => 'Employees', 'permission' => $employee, 'filters' => [], 'about' => 'Head count of each department.'],
            'employees.designation' => ['title' => 'Designation-wise employees', 'group' => 'Employees', 'permission' => $employee, 'filters' => [], 'about' => 'Head count of each designation.'],

            'attendance.daily' => ['title' => 'Daily attendance', 'group' => 'Attendance', 'permission' => $attendance, 'filters' => ['date', 'department_id'], 'about' => 'Who came, when, and for how long on one day.'],
            'attendance.monthly' => ['title' => 'Monthly attendance summary', 'group' => 'Attendance', 'permission' => $attendance, 'filters' => ['month', 'department_id'], 'about' => 'Present, late, half-day, absent and leave days of every employee in a month.'],
            'attendance.employee' => ['title' => 'Employee-wise attendance', 'group' => 'Attendance', 'permission' => $attendance, 'filters' => ['employee_id', 'month'], 'about' => 'Day-by-day attendance of one employee.'],
            'attendance.department' => ['title' => 'Department-wise attendance', 'group' => 'Attendance', 'permission' => $attendance, 'filters' => ['month'], 'about' => 'Attendance totals of each department for a month.'],
            'attendance.late' => ['title' => 'Late report', 'group' => 'Attendance', 'permission' => $attendance, 'filters' => ['from', 'to', 'department_id'], 'about' => 'Late arrivals between two dates.'],
            'attendance.absent' => ['title' => 'Absent report', 'group' => 'Attendance', 'permission' => $attendance, 'filters' => ['from', 'to', 'department_id'], 'about' => 'Absences between two dates.'],

            'leave.history' => ['title' => 'Leave history', 'group' => 'Leave', 'permission' => $leave, 'filters' => ['from', 'to', 'leave_status', 'leave_type_id', 'department_id', 'employee_id'], 'about' => 'All leave requests that overlap the chosen dates.'],
            'leave.pending' => ['title' => 'Pending leave requests', 'group' => 'Leave', 'permission' => $leave, 'filters' => ['from', 'to', 'leave_type_id', 'department_id', 'employee_id'], 'about' => 'Requests waiting for a decision.'],
            'leave.approved' => ['title' => 'Approved leave', 'group' => 'Leave', 'permission' => $leave, 'filters' => ['from', 'to', 'leave_type_id', 'department_id', 'employee_id'], 'about' => 'Approved leave in the chosen dates.'],
            'leave.rejected' => ['title' => 'Rejected leave', 'group' => 'Leave', 'permission' => $leave, 'filters' => ['from', 'to', 'leave_type_id', 'department_id', 'employee_id'], 'about' => 'Rejected requests in the chosen dates.'],
            'leave.employee' => ['title' => 'Employee-wise leave', 'group' => 'Leave', 'permission' => $leave, 'filters' => ['year', 'department_id'], 'about' => 'Leave days taken by each employee in a year.'],

            'payroll.monthly' => ['title' => 'Monthly payroll', 'group' => 'Payroll', 'permission' => $payroll, 'filters' => ['period'], 'about' => 'Every employee\'s salary for one payroll month.'],
            'payroll.employee' => ['title' => 'Employee payroll history', 'group' => 'Payroll', 'permission' => $payroll, 'filters' => ['employee_id', 'year'], 'about' => 'One employee\'s salary month by month.'],
            'payroll.department' => ['title' => 'Department payroll', 'group' => 'Payroll', 'permission' => $payroll, 'filters' => ['period'], 'about' => 'Salary totals of each department for a month.'],
            'payroll.expense' => ['title' => 'Salary expense', 'group' => 'Payroll', 'permission' => $payroll, 'filters' => ['year'], 'about' => 'What salaries cost the organization each month of a year.'],
            'payroll.deductions' => ['title' => 'Deduction report', 'group' => 'Payroll', 'permission' => $payroll, 'filters' => ['period'], 'about' => 'Total of each kind of deduction for a month.'],
            'payroll.tax' => ['title' => 'Tax report', 'group' => 'Payroll', 'permission' => $payroll, 'filters' => ['period'], 'about' => 'Income tax deducted from each employee in a month.'],
            'payroll.paid' => ['title' => 'Paid payroll', 'group' => 'Payroll', 'permission' => $payroll, 'filters' => ['period'], 'about' => 'Salaries that have been paid, with method and reference.'],
            'payroll.pending' => ['title' => 'Pending payroll', 'group' => 'Payroll', 'permission' => $payroll, 'filters' => ['period'], 'about' => 'Salaries that have not been paid yet.'],
        ];
    }

    /** Reports this user may open, grouped for the index page. */
    public function groups(User $user): array
    {
        $groups = [];

        foreach ($this->definitions() as $key => $definition) {
            if ($user->hasPermission($definition['permission'])) {
                $groups[$definition['group']][$key] = $definition;
            }
        }

        return $groups;
    }

    // ------------------------------------------------------------------
    //  Filters
    // ------------------------------------------------------------------

    private const FIELDS = [
        'date' => ['label' => 'Date', 'input' => 'date'],
        'from' => ['label' => 'From', 'input' => 'date'],
        'to' => ['label' => 'To', 'input' => 'date'],
        'month' => ['label' => 'Month', 'input' => 'month'],
        'year' => ['label' => 'Year', 'input' => 'number'],
        'period' => ['label' => 'Payroll month', 'input' => 'select', 'options' => 'periods', 'blank' => null],
        'department_id' => ['label' => 'Department', 'input' => 'select', 'options' => 'departments', 'blank' => 'All departments'],
        'designation_id' => ['label' => 'Designation', 'input' => 'select', 'options' => 'designations', 'blank' => 'All designations'],
        'employment_type' => ['label' => 'Employment type', 'input' => 'select', 'options' => 'employmentTypes', 'blank' => 'All types'],
        'status' => ['label' => 'Status', 'input' => 'select', 'options' => 'employeeStatuses', 'blank' => 'All statuses'],
        'leave_status' => ['label' => 'Status', 'input' => 'select', 'options' => 'leaveStatuses', 'blank' => 'All statuses'],
        'leave_type_id' => ['label' => 'Leave type', 'input' => 'select', 'options' => 'leaveTypes', 'blank' => 'All leave types'],
        'employee_id' => ['label' => 'Employee', 'input' => 'select', 'options' => 'employees', 'blank' => 'Choose an employee'],
    ];

    /** Reads and cleans the filters a report uses. Anything missing or invalid falls back to a default. */
    public function filters(string $key, array $input): array
    {
        $clean = [];

        foreach ($this->definitions()[$key]['filters'] as $name) {
            $raw = $input[$name] ?? null;

            $clean[$name] = match ($name) {
                'date' => $this->dateOr($raw, today()),
                'from' => $this->dateOr($raw, now()->startOfMonth()),
                'to' => $this->dateOr($raw, today()),
                'month' => $this->monthOr($raw),
                'year' => max(2000, min(2100, (int) ($raw ?: now()->year))),
                'period' => $this->periodOr($raw),
                'department_id', 'designation_id', 'leave_type_id', 'employee_id' => $raw ? (int) $raw : null,
                'employment_type' => array_key_exists((string) $raw, Employee::EMPLOYMENT_TYPES) ? $raw : null,
                'status' => array_key_exists((string) $raw, Employee::STATUSES) ? $raw : null,
                'leave_status' => in_array($raw, ['pending', 'approved', 'rejected', 'cancelled'], true) ? $raw : null,
            };
        }

        return $clean;
    }

    /** What the filter form needs: input type, label and the choices of each select. */
    public function fields(string $key): array
    {
        $options = $this->options();
        $fields = [];

        foreach ($this->definitions()[$key]['filters'] as $name) {
            $field = self::FIELDS[$name];
            $field['choices'] = isset($field['options']) ? $options[$field['options']] : null;
            $fields[$name] = $field;
        }

        return $fields;
    }

    /** "Month: September 2026 · Department: Finance" for the top of PDF and CSV files. */
    public function describeFilters(string $key, array $filters): string
    {
        $parts = [];

        foreach ($this->fields($key) as $name => $field) {
            $value = $filters[$name] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $text = match ($field['input']) {
                'month' => Carbon::createFromFormat('!Y-m', $value)->format('F Y'),
                'date' => Carbon::parse($value)->format('d M Y'),
                'select' => $field['choices'][$value] ?? $value,
                default => (string) $value,
            };

            $parts[] = $field['label'] . ': ' . $text;
        }

        return implode('  ·  ', $parts);
    }

    private function options(): array
    {
        return $this->optionCache ??= [
            'departments' => Department::orderBy('name')->pluck('name', 'id')->all(),
            'designations' => Designation::orderBy('name')->pluck('name', 'id')->all(),
            'leaveTypes' => LeaveType::orderBy('name')->pluck('name', 'id')->all(),
            'employmentTypes' => Employee::EMPLOYMENT_TYPES,
            'employeeStatuses' => Employee::STATUSES,
            'leaveStatuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'],
            'employees' => Employee::orderBy('employee_code')->get()->mapWithKeys(fn ($e) => [$e->id => $e->employee_code . ' · ' . $e->full_name])->all(),
            'periods' => PayrollPeriod::whereHas('payrolls')->orderByDesc('year')->orderByDesc('month')->get()
                ->mapWithKeys(fn ($p) => [$p->id => $p->label . ' (' . ucfirst($p->status) . ')'])->all(),
        ];
    }

    private function dateOr(mixed $value, Carbon $default): string
    {
        try {
            return $value ? Carbon::createFromFormat('!Y-m-d', (string) $value)->format('Y-m-d') : $default->format('Y-m-d');
        } catch (Throwable) {
            return $default->format('Y-m-d');
        }
    }

    private function monthOr(mixed $value): string
    {
        try {
            return $value ? Carbon::createFromFormat('!Y-m', (string) $value)->format('Y-m') : now()->format('Y-m');
        } catch (Throwable) {
            return now()->format('Y-m');
        }
    }

    private function periodOr(mixed $value): ?int
    {
        $periods = $this->options()['periods'];

        return $value && isset($periods[(int) $value]) ? (int) $value : (array_key_first($periods) ?: null);
    }

    // ------------------------------------------------------------------
    //  Running a report
    // ------------------------------------------------------------------

    public function run(string $key, array $f): array
    {
        return match ($key) {
            'employees.all' => $this->employeeList($f, null),
            'employees.active' => $this->employeeList($f, 'active'),
            'employees.department' => $this->headCount(Department::class, 'Department'),
            'employees.designation' => $this->headCount(Designation::class, 'Designation'),

            'attendance.daily' => $this->attendanceDaily($f),
            'attendance.monthly' => $this->attendanceMonthly($f),
            'attendance.employee' => $this->attendanceEmployee($f),
            'attendance.department' => $this->attendanceDepartment($f),
            'attendance.late' => $this->attendanceExceptions($f, 'late'),
            'attendance.absent' => $this->attendanceExceptions($f, 'absent'),

            'leave.history' => $this->leaveList($f, $f['leave_status'] ?? null),
            'leave.pending' => $this->leaveList($f, 'pending'),
            'leave.approved' => $this->leaveList($f, 'approved'),
            'leave.rejected' => $this->leaveList($f, 'rejected'),
            'leave.employee' => $this->leaveByEmployee($f),

            'payroll.monthly' => $this->payrollMonthly($f),
            'payroll.employee' => $this->payrollEmployee($f),
            'payroll.department' => $this->payrollDepartment($f),
            'payroll.expense' => $this->payrollExpense($f),
            'payroll.deductions' => $this->payrollDeductions($f),
            'payroll.tax' => $this->payrollTax($f),
            'payroll.paid' => $this->payrollPaid($f),
            'payroll.pending' => $this->payrollPending($f),
        };
    }

    // ----- employees -----

    private function employeeList(array $f, ?string $forceStatus): array
    {
        $employees = Employee::with('department', 'designation')
            ->when($f['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($f['designation_id'] ?? null, fn ($q, $v) => $q->where('designation_id', $v))
            ->when($f['employment_type'] ?? null, fn ($q, $v) => $q->where('employment_type', $v))
            ->when($forceStatus ?? ($f['status'] ?? null), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('employee_code')->get();

        return $this->result([
            $this->col('code', 'Employee ID'), $this->col('name', 'Name'), $this->col('department', 'Department'),
            $this->col('designation', 'Designation'), $this->col('type', 'Type'), $this->col('joined', 'Joined', 'date'),
            $this->col('phone', 'Phone'), $this->col('status', 'Status', 'status'),
        ], $employees->map(fn ($e) => [
            'code' => $e->employee_code, 'name' => $e->full_name, 'department' => $e->department?->name,
            'designation' => $e->designation?->name, 'type' => Employee::EMPLOYMENT_TYPES[$e->employment_type] ?? $e->employment_type,
            'joined' => $e->joining_date->format('Y-m-d'), 'phone' => $e->phone, 'status' => $e->status,
        ])->all(), null, [$employees->count() . ' employee(s)']);
    }

    private function headCount(string $model, string $label): array
    {
        $rows = $model::withCount(['employees', 'employees as active_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')->get()
            ->map(fn ($m) => ['name' => $m->name, 'total' => $m->employees_count, 'active' => $m->active_count, 'other' => $m->employees_count - $m->active_count])
            ->all();

        return $this->result(
            [$this->col('name', $label), $this->col('total', 'Total employees', 'number'), $this->col('active', 'Active', 'number'), $this->col('other', 'Not active', 'number')],
            $rows, $this->sum($rows, ['total', 'active', 'other'])
        );
    }

    // ----- attendance -----

    private function attendanceDaily(array $f): array
    {
        $records = Attendance::with('employee.department')->whereDate('date', $f['date'])
            ->when($f['department_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->get()->filter(fn ($r) => $r->employee)->sortBy(fn ($r) => $r->employee->employee_code)->values();

        $counts = $records->groupBy('status')->map->count();
        $notes = [$records->count() . ' record(s)', collect(Attendance::STATUSES)->map(fn ($s) => ucwords(str_replace('_', ' ', $s)) . ' ' . ($counts[$s] ?? 0))->implode(' · ')];

        return $this->result([
            $this->col('code', 'Employee ID'), $this->col('name', 'Name'), $this->col('department', 'Department'),
            $this->col('in', 'Check in'), $this->col('out', 'Check out'), $this->col('hours', 'Hours', 'number'),
            $this->col('status', 'Status', 'status'), $this->col('remarks', 'Remarks'),
        ], $records->map(fn ($r) => [
            'code' => $r->employee->employee_code, 'name' => $r->employee->full_name, 'department' => $r->employee->department?->name,
            'in' => $r->check_in ? substr($r->check_in, 0, 5) : null, 'out' => $r->check_out ? substr($r->check_out, 0, 5) : null,
            'hours' => (float) $r->working_hours, 'status' => $r->status, 'remarks' => $r->remarks,
        ])->all(), null, $notes);
    }

    private function attendanceMonthly(array $f): array
    {
        $start = Carbon::createFromFormat('!Y-m', $f['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $employees = Employee::with('department')
            ->where(fn ($q) => $q->where('status', 'active')->orWhereHas('attendances', fn ($a) => $a->whereBetween('date', [$start->toDateString(), $end->toDateString()])))
            ->whereDate('joining_date', '<=', $end->toDateString())
            ->when($f['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('employee_code')->get();

        $records = Attendance::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('employee_id', $employees->pluck('id'))->get()->groupBy('employee_id');

        $rows = $employees->map(function ($e) use ($records) {
            $mine = $records->get($e->id, collect());
            $count = $mine->groupBy('status')->map->count();

            return [
                'code' => $e->employee_code, 'name' => $e->full_name, 'department' => $e->department?->name,
                'present' => $count['present'] ?? 0, 'late' => $count['late'] ?? 0, 'half' => $count['half_day'] ?? 0,
                'absent' => $count['absent'] ?? 0, 'leave' => $count['leave'] ?? 0, 'hours' => round((float) $mine->sum('working_hours'), 2),
            ];
        })->all();

        return $this->result([
            $this->col('code', 'Employee ID'), $this->col('name', 'Name'), $this->col('department', 'Department'),
            $this->col('present', 'Present', 'number'), $this->col('late', 'Late', 'number'), $this->col('half', 'Half day', 'number'),
            $this->col('absent', 'Absent', 'number'), $this->col('leave', 'Leave', 'number'), $this->col('hours', 'Hours worked', 'number'),
        ], $rows, $this->sum($rows, ['present', 'late', 'half', 'absent', 'leave', 'hours']));
    }

    private function attendanceEmployee(array $f): array
    {
        $columns = [
            $this->col('date', 'Date', 'date'), $this->col('day', 'Day'), $this->col('in', 'Check in'), $this->col('out', 'Check out'),
            $this->col('hours', 'Hours', 'number'), $this->col('status', 'Status', 'status'), $this->col('remarks', 'Remarks'),
        ];

        if (! $f['employee_id']) {
            return $this->result($columns, [], null, ['Choose an employee to see their attendance.']);
        }

        $start = Carbon::createFromFormat('!Y-m', $f['month'])->startOfMonth();
        $records = Attendance::where('employee_id', $f['employee_id'])
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->orderBy('date')->get();

        $rows = $records->map(fn ($r) => [
            'date' => $r->date->format('Y-m-d'), 'day' => $r->date->format('D'),
            'in' => $r->check_in ? substr($r->check_in, 0, 5) : null, 'out' => $r->check_out ? substr($r->check_out, 0, 5) : null,
            'hours' => (float) $r->working_hours, 'status' => $r->status, 'remarks' => $r->remarks,
        ])->all();

        return $this->result($columns, $rows, ['hours' => round((float) $records->sum('working_hours'), 2)]);
    }

    private function attendanceDepartment(array $f): array
    {
        $start = Carbon::createFromFormat('!Y-m', $f['month'])->startOfMonth();

        $data = Attendance::join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->whereBetween('attendances.date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->selectRaw('employees.department_id as dept, attendances.status as st, COUNT(*) as c, SUM(attendances.working_hours) as h')
            ->groupBy('employees.department_id', 'attendances.status')->get()->groupBy('dept');

        $rows = Department::orderBy('name')->get()->map(function ($d) use ($data) {
            $set = $data->get($d->id, collect())->keyBy('st');

            return [
                'department' => $d->name, 'present' => (int) ($set['present']->c ?? 0), 'late' => (int) ($set['late']->c ?? 0),
                'half' => (int) ($set['half_day']->c ?? 0), 'absent' => (int) ($set['absent']->c ?? 0), 'leave' => (int) ($set['leave']->c ?? 0),
                'hours' => round((float) $set->sum('h'), 2),
            ];
        })->all();

        return $this->result([
            $this->col('department', 'Department'), $this->col('present', 'Present', 'number'), $this->col('late', 'Late', 'number'),
            $this->col('half', 'Half day', 'number'), $this->col('absent', 'Absent', 'number'), $this->col('leave', 'Leave', 'number'),
            $this->col('hours', 'Hours worked', 'number'),
        ], $rows, $this->sum($rows, ['present', 'late', 'half', 'absent', 'leave', 'hours']));
    }

    private function attendanceExceptions(array $f, string $status): array
    {
        $records = Attendance::with('employee.department')->where('status', $status)
            ->whereBetween('date', [$f['from'], $f['to']])
            ->when($f['department_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->orderBy('date')->get()->filter(fn ($r) => $r->employee)->values();

        $columns = [$this->col('date', 'Date', 'date'), $this->col('code', 'Employee ID'), $this->col('name', 'Name'), $this->col('department', 'Department')];
        $columns[] = $status === 'late' ? $this->col('in', 'Check in') : $this->col('remarks', 'Remarks');

        $rows = $records->map(fn ($r) => [
            'date' => $r->date->format('Y-m-d'), 'code' => $r->employee->employee_code, 'name' => $r->employee->full_name,
            'department' => $r->employee->department?->name, 'in' => $r->check_in ? substr($r->check_in, 0, 5) : null, 'remarks' => $r->remarks,
        ])->all();

        return $this->result($columns, $rows, null, [count($rows) . ' record(s)']);
    }

    // ----- leave -----

    private function leaveList(array $f, ?string $status): array
    {
        $requests = LeaveRequest::with('employee.department', 'leaveType', 'approver')
            ->whereDate('start_date', '<=', $f['to'])->whereDate('end_date', '>=', $f['from'])
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($f['leave_type_id'] ?? null, fn ($q, $v) => $q->where('leave_type_id', $v))
            ->when($f['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($f['department_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->orderByDesc('start_date')->get()->filter(fn ($r) => $r->employee)->values();

        $rows = $requests->map(fn ($r) => [
            'code' => $r->employee->employee_code, 'name' => $r->employee->full_name, 'department' => $r->employee->department?->name,
            'type' => $r->leaveType->name, 'from' => $r->start_date->format('Y-m-d'), 'to' => $r->end_date->format('Y-m-d'),
            'days' => (float) $r->days, 'status' => $r->status, 'applied' => $r->created_at->format('Y-m-d'), 'by' => $r->approver?->name,
        ])->all();

        return $this->result([
            $this->col('code', 'Employee ID'), $this->col('name', 'Name'), $this->col('department', 'Department'), $this->col('type', 'Leave type'),
            $this->col('from', 'From', 'date'), $this->col('to', 'To', 'date'), $this->col('days', 'Days', 'number'),
            $this->col('status', 'Status', 'status'), $this->col('applied', 'Applied on', 'date'), $this->col('by', 'Decided by'),
        ], $rows, $this->sum($rows, ['days']), [count($rows) . ' request(s)']);
    }

    private function leaveByEmployee(array $f): array
    {
        $requests = LeaveRequest::with('employee.department', 'leaveType')->whereYear('start_date', $f['year'])
            ->when($f['department_id'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->get()->filter(fn ($r) => $r->employee)->groupBy('employee_id');

        $rows = $requests->map(function ($set) {
            $employee = $set->first()->employee;
            $approved = $set->where('status', 'approved');

            return [
                'code' => $employee->employee_code, 'name' => $employee->full_name, 'department' => $employee->department?->name,
                'approved' => (float) $approved->sum('days'),
                'unpaid' => (float) $approved->filter(fn ($r) => ! $r->leaveType->is_paid)->sum('days'),
                'pending' => (float) $set->where('status', 'pending')->sum('days'),
                'rejected' => $set->where('status', 'rejected')->count(),
            ];
        })->sortBy('code')->values()->all();

        return $this->result([
            $this->col('code', 'Employee ID'), $this->col('name', 'Name'), $this->col('department', 'Department'),
            $this->col('approved', 'Approved days', 'number'), $this->col('unpaid', 'of which unpaid', 'number'),
            $this->col('pending', 'Pending days', 'number'), $this->col('rejected', 'Rejected requests', 'number'),
        ], $rows, $this->sum($rows, ['approved', 'unpaid', 'pending', 'rejected']));
    }

    // ----- payroll -----

    private function payrollMonthly(array $f): array
    {
        $columns = [
            $this->col('code', 'Employee ID'), $this->col('name', 'Name'), $this->col('department', 'Department'),
            $this->col('basic', 'Basic', 'money'), $this->col('allowances', 'Allowances', 'money'), $this->col('overtime', 'Overtime', 'money'),
            $this->col('bonus', 'Bonus', 'money'), $this->col('gross', 'Gross', 'money'), $this->col('tax', 'Tax', 'money'),
            $this->col('deductions', 'Total deductions', 'money'), $this->col('net', 'Net salary', 'money'),
        ];

        $period = $this->periodOrNull($f);
        if (! $period) {
            return $this->result($columns, [], null, ['No payroll has been processed yet.']);
        }

        $rows = $this->payrollsOf($period)->map(fn ($p) => [
            'code' => $p->employee->employee_code, 'name' => $p->employee->full_name, 'department' => $p->employee->department?->name,
            'basic' => (float) $p->basic_salary, 'allowances' => (float) $p->total_allowances, 'overtime' => (float) $p->overtime,
            'bonus' => (float) $p->bonus, 'gross' => (float) $p->gross_salary, 'tax' => (float) $p->tax,
            'deductions' => (float) $p->total_deductions, 'net' => (float) $p->net_salary,
        ])->all();

        return $this->result($columns, $rows, $this->sum($rows, ['basic', 'allowances', 'overtime', 'bonus', 'gross', 'tax', 'deductions', 'net']), ['Status: ' . ucfirst($period->status)]);
    }

    private function payrollEmployee(array $f): array
    {
        $columns = [
            $this->col('month', 'Month'), $this->col('basic', 'Basic', 'money'), $this->col('allowances', 'Allowances', 'money'),
            $this->col('overtime', 'Overtime', 'money'), $this->col('bonus', 'Bonus', 'money'), $this->col('gross', 'Gross', 'money'),
            $this->col('tax', 'Tax', 'money'), $this->col('deductions', 'Total deductions', 'money'), $this->col('net', 'Net salary', 'money'),
            $this->col('status', 'Period status', 'status'),
        ];

        if (! $f['employee_id']) {
            return $this->result($columns, [], null, ['Choose an employee to see their payroll history.']);
        }

        $payrolls = Payroll::with('period')->where('employee_id', $f['employee_id'])
            ->whereHas('period', fn ($q) => $q->where('year', $f['year']))->get()
            ->sortBy(fn ($p) => $p->period->month)->values();

        $rows = $payrolls->map(fn ($p) => [
            'month' => $p->period->label, 'basic' => (float) $p->basic_salary, 'allowances' => (float) $p->total_allowances,
            'overtime' => (float) $p->overtime, 'bonus' => (float) $p->bonus, 'gross' => (float) $p->gross_salary, 'tax' => (float) $p->tax,
            'deductions' => (float) $p->total_deductions, 'net' => (float) $p->net_salary, 'status' => $p->period->status,
        ])->all();

        return $this->result($columns, $rows, $this->sum($rows, ['basic', 'allowances', 'overtime', 'bonus', 'gross', 'tax', 'deductions', 'net']));
    }

    private function payrollDepartment(array $f): array
    {
        $columns = [
            $this->col('department', 'Department'), $this->col('employees', 'Employees', 'number'), $this->col('gross', 'Gross', 'money'),
            $this->col('tax', 'Tax', 'money'), $this->col('deductions', 'Total deductions', 'money'), $this->col('net', 'Net salary', 'money'),
        ];

        $period = $this->periodOrNull($f);
        if (! $period) {
            return $this->result($columns, [], null, ['No payroll has been processed yet.']);
        }

        $rows = $this->payrollsOf($period)->groupBy(fn ($p) => $p->employee->department?->name ?? 'No department')
            ->map(fn ($set, $name) => [
                'department' => $name, 'employees' => $set->count(), 'gross' => round((float) $set->sum('gross_salary'), 2),
                'tax' => round((float) $set->sum('tax'), 2), 'deductions' => round((float) $set->sum('total_deductions'), 2), 'net' => round((float) $set->sum('net_salary'), 2),
            ])->sortBy('department')->values()->all();

        return $this->result($columns, $rows, $this->sum($rows, ['employees', 'gross', 'tax', 'deductions', 'net']), ['Status: ' . ucfirst($period->status)]);
    }

    private function payrollExpense(array $f): array
    {
        $periods = PayrollPeriod::where('year', $f['year'])->withCount('payrolls')
            ->withSum('payrolls', 'gross_salary')->withSum('payrolls', 'tax')->withSum('payrolls', 'total_deductions')->withSum('payrolls', 'net_salary')
            ->orderBy('month')->get()->filter(fn ($p) => $p->payrolls_count > 0);

        $rows = $periods->map(fn ($p) => [
            'month' => $p->label, 'employees' => $p->payrolls_count, 'gross' => (float) $p->payrolls_sum_gross_salary,
            'tax' => (float) $p->payrolls_sum_tax, 'deductions' => (float) $p->payrolls_sum_total_deductions,
            'net' => (float) $p->payrolls_sum_net_salary, 'status' => $p->status,
        ])->values()->all();

        return $this->result([
            $this->col('month', 'Month'), $this->col('employees', 'Employees', 'number'), $this->col('gross', 'Gross salary', 'money'),
            $this->col('tax', 'Tax', 'money'), $this->col('deductions', 'Total deductions', 'money'), $this->col('net', 'Net paid out', 'money'),
            $this->col('status', 'Status', 'status'),
        ], $rows, $this->sum($rows, ['gross', 'tax', 'deductions', 'net']), ['Gross salary is the cost of salaries before deductions.']);
    }

    private function payrollDeductions(array $f): array
    {
        $columns = [$this->col('name', 'Deduction'), $this->col('employees', 'Employees', 'number'), $this->col('total', 'Total amount', 'money')];

        $period = $this->periodOrNull($f);
        if (! $period) {
            return $this->result($columns, [], null, ['No payroll has been processed yet.']);
        }

        // Lines like "Absence Deduction (2 days)" are grouped under their component name.
        $rows = PayrollItem::with('component')->where('type', 'deduction')
            ->whereHas('payroll', fn ($q) => $q->where('payroll_period_id', $period->id))->get()
            ->groupBy(fn ($i) => $i->component?->name ?? trim(preg_replace('/\s*\(.*\)$/', '', $i->name)))
            ->map(fn ($set, $name) => ['name' => $name, 'employees' => $set->pluck('payroll_id')->unique()->count(), 'total' => round((float) $set->sum('amount'), 2)])
            ->sortByDesc('total')->values()->all();

        return $this->result($columns, $rows, ['total' => round(array_sum(array_column($rows, 'total')), 2)], ['Status: ' . ucfirst($period->status)]);
    }

    private function payrollTax(array $f): array
    {
        $columns = [
            $this->col('code', 'Employee ID'), $this->col('name', 'Name'), $this->col('department', 'Department'),
            $this->col('gross', 'Gross salary', 'money'), $this->col('tax', 'Income tax', 'money'), $this->col('net', 'Net salary', 'money'),
        ];

        $period = $this->periodOrNull($f);
        if (! $period) {
            return $this->result($columns, [], null, ['No payroll has been processed yet.']);
        }

        $rows = $this->payrollsOf($period)->sortByDesc('tax')->map(fn ($p) => [
            'code' => $p->employee->employee_code, 'name' => $p->employee->full_name, 'department' => $p->employee->department?->name,
            'gross' => (float) $p->gross_salary, 'tax' => (float) $p->tax, 'net' => (float) $p->net_salary,
        ])->values()->all();

        return $this->result($columns, $rows, $this->sum($rows, ['gross', 'tax', 'net']), ['Tax is worked out from the slabs saved in the system.']);
    }

    private function payrollPaid(array $f): array
    {
        $columns = [
            $this->col('code', 'Employee ID'), $this->col('name', 'Name'), $this->col('method', 'Method'), $this->col('bank', 'Bank / service'),
            $this->col('account', 'Account'), $this->col('reference', 'Reference'), $this->col('date', 'Paid on', 'date'), $this->col('amount', 'Amount', 'money'),
        ];

        $period = $this->periodOrNull($f);
        if (! $period) {
            return $this->result($columns, [], null, ['No payroll has been processed yet.']);
        }

        $payments = Payment::with('employee')->where('status', 'paid')
            ->whereHas('payroll', fn ($q) => $q->where('payroll_period_id', $period->id))
            ->orderBy('payment_date')->orderBy('employee_id')->get()->filter(fn ($p) => $p->employee)->values();

        $rows = $payments->map(fn ($p) => [
            'code' => $p->employee->employee_code, 'name' => $p->employee->full_name, 'method' => PaymentService::METHODS[$p->method] ?? $p->method,
            'bank' => $p->bank_name, 'account' => $this->mask($p->account_number), 'reference' => $p->transaction_reference,
            'date' => $p->payment_date?->format('Y-m-d'), 'amount' => (float) $p->amount,
        ])->all();

        return $this->result($columns, $rows, $this->sum($rows, ['amount']), [count($rows) . ' payment(s)']);
    }

    private function payrollPending(array $f): array
    {
        $columns = [
            $this->col('code', 'Employee ID'), $this->col('name', 'Name'), $this->col('department', 'Department'),
            $this->col('net', 'Net salary due', 'money'), $this->col('payment', 'Payment'),
        ];

        $period = $this->periodOrNull($f);
        if (! $period) {
            return $this->result($columns, [], null, ['No payroll has been processed yet.']);
        }

        $payrolls = $this->payrollsOf($period, ['payments'])
            ->filter(fn ($p) => ! $p->payments->contains('status', 'paid'))->values();

        $rows = $payrolls->map(function ($p) {
            $latest = $p->payments->where('status', '!=', 'cancelled')->sortByDesc('id')->first();

            return [
                'code' => $p->employee->employee_code, 'name' => $p->employee->full_name, 'department' => $p->employee->department?->name,
                'net' => (float) $p->net_salary, 'payment' => $latest ? ucfirst($latest->status) : 'Not prepared',
            ];
        })->all();

        return $this->result($columns, $rows, $this->sum($rows, ['net']), ['Status of the month: ' . ucfirst($period->status)]);
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    private function periodOrNull(array $f): ?PayrollPeriod
    {
        return $f['period'] ? PayrollPeriod::find($f['period']) : null;
    }

    private function payrollsOf(PayrollPeriod $period, array $with = [])
    {
        return Payroll::with(array_merge(['employee.department'], $with))
            ->where('payroll_period_id', $period->id)->get()
            ->filter(fn ($p) => $p->employee)
            ->sortBy(fn ($p) => $p->employee->employee_code)->values();
    }

    private function col(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    private function sum(array $rows, array $keys): array
    {
        $totals = [];
        foreach ($keys as $key) {
            $totals[$key] = round(array_sum(array_column($rows, $key)), 2);
        }

        return $totals;
    }

    private function result(array $columns, array $rows, ?array $totals = null, array $notes = []): array
    {
        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals, 'notes' => $notes];
    }

    private function mask(?string $account): ?string
    {
        return $account ? str_repeat('*', max(0, strlen($account) - 4)) . substr($account, -4) : null;
    }
}
