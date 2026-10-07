<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Services\AuditService;
use App\Services\SalaryService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function __construct(private SalaryService $salary)
    {
    }

    public function index(Request $request)
    {
        $employees = Employee::with('department', 'designation')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . trim($request->q) . '%';
                $query->where(function ($inner) use ($term) {
                    $inner->whereRaw("CONCAT(first_name, ' ', last_name) like ?", [$term])
                        ->orWhere('employee_code', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('nid', 'like', $term);
                });
            })
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('designation_id'), fn ($q) => $q->where('designation_id', $request->designation_id))
            ->when($request->filled('employment_type'), fn ($q) => $q->where('employment_type', $request->employment_type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('employee_code')
            ->paginate(10)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'designations' => Designation::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        $employee = new Employee(['status' => 'active', 'employment_type' => 'permanent']);

        return view('employees.form', $this->formData($employee) + ['nextCode' => Employee::nextCode()]);
    }

    public function store(EmployeeRequest $request)
    {
        $data = Arr::except($request->validated(), ['photo']);
        $data['employee_code'] = Employee::nextCode();
        $data['status'] = $data['status'] ?? 'active';

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->storePhoto($request->file('photo'));
        }

        $employee = Employee::create($data);

        AuditService::log(
            'created', 'employees',
            "Employee {$employee->employee_code} ({$employee->full_name}) was added",
            $employee->id
        );

        return redirect()->route('employees.show', $employee)
            ->with('success', "{$employee->full_name} has been added as {$employee->employee_code}.");
    }

    public function show(Employee $employee)
    {
        $user = auth()->user();
        $employee->load('department', 'designation');

        $attendance = $user->hasPermission('attendance.view')
            ? $employee->attendances()->orderByDesc('date')->limit(15)->get() : collect();

        $leaves = $user->hasPermission('leave.view')
            ? $employee->leaveRequests()->with('leaveType')->orderByDesc('start_date')->limit(15)->get() : collect();

        $payrolls = $user->hasPermission('payroll.view')
            ? $employee->payrolls()->with('period')->orderByDesc('id')->limit(12)->get() : collect();

        $structure = collect();
        $salaryChanges = collect();

        if ($user->hasPermission('salary.view')) {
            $structure = $this->salary->structure($employee);

            $salaryChanges = AuditLog::with('user')
                ->where('module', 'employees')->where('action', 'salary_changed')->where('record_id', $employee->id)
                ->latest('id')->limit(10)->get();
        }

        return view('employees.show', compact('employee', 'attendance', 'leaves', 'payrolls', 'structure', 'salaryChanges'));
    }

    public function edit(Employee $employee)
    {
        return view('employees.form', $this->formData($employee) + ['nextCode' => $employee->employee_code]);
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $data = Arr::except($request->validated(), ['photo']);

        // Salary changes go through the Salary page; here only users with salary.manage may change it.
        if (! $request->user()->hasPermission('salary.manage')) {
            unset($data['basic_salary']);
        }

        if ($request->hasFile('photo')) {
            $this->deletePhoto($employee->photo);
            $data['photo'] = $this->storePhoto($request->file('photo'));
        }

        $employee->fill($data);
        $changed = $employee->getDirty();
        $oldSalary = $employee->getOriginal('basic_salary');
        $oldStatus = $employee->getOriginal('status');
        $employee->save();

        if (array_key_exists('basic_salary', $changed)) {
            AuditService::log(
                'salary_changed', 'employees',
                sprintf('Basic salary of %s changed from ৳%s to ৳%s', $employee->employee_code, number_format((float) $oldSalary, 2), number_format((float) $employee->basic_salary, 2)),
                $employee->id
            );
        }

        if (array_key_exists('status', $changed)) {
            AuditService::log(
                'status_changed', 'employees',
                "Status of {$employee->employee_code} changed from {$oldStatus} to {$employee->status}",
                $employee->id
            );
        }

        $other = array_diff(array_keys($changed), ['basic_salary', 'status', 'updated_at']);
        if ($other) {
            AuditService::log(
                'updated', 'employees',
                "Employee {$employee->employee_code} updated (" . implode(', ', $other) . ')',
                $employee->id
            );
        }

        return redirect()->route('employees.show', $employee)
            ->with('success', "{$employee->full_name}'s record has been updated.");
    }

    private function formData(Employee $employee): array
    {
        return [
            'employee' => $employee,
            // Only active departments/designations can be chosen, plus the one the employee already has.
            'departments' => Department::where('status', 'active')->orWhere('id', $employee->department_id)->orderBy('name')->pluck('name', 'id'),
            'designations' => Designation::where('status', 'active')->orWhere('id', $employee->designation_id)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function storePhoto(UploadedFile $file): string
    {
        // Random name + extension taken from the real file type, never from the uploaded name.
        $name = Str::uuid() . '.' . $file->extension();
        $file->move(public_path('uploads/employees'), $name);

        return $name;
    }

    private function deletePhoto(?string $name): void
    {
        if ($name) {
            File::delete(public_path('uploads/employees/' . basename($name)));
        }
    }
}
