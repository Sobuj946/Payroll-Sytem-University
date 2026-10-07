<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryComponent;
use App\Services\AuditService;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Salary structure of each employee. */
class SalaryController extends Controller
{
    public function __construct(private SalaryService $salary)
    {
    }

    public function index(Request $request)
    {
        $employees = Employee::with('department', 'designation')
            ->whereIn('status', ['active', 'on_leave', 'suspended'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . trim($request->q) . '%';
                $query->where(function ($inner) use ($term) {
                    $inner->whereRaw("CONCAT(first_name, ' ', last_name) like ?", [$term])->orWhere('employee_code', 'like', $term);
                });
            })
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->orderBy('employee_code')
            ->paginate(15)
            ->withQueryString();

        $totals = $employees->getCollection()->mapWithKeys(fn ($e) => [$e->id => $this->salary->totals($e)]);

        return view('salary.index', [
            'employees' => $employees,
            'totals' => $totals,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Employee $employee)
    {
        $employee->load('department', 'designation');

        $lines = $this->salary->structure($employee);
        $currentIds = $lines->pluck('row.id')->all();

        $history = $employee->salaryComponents()->with('component')
            ->whereNotIn('id', $currentIds)
            ->orderByDesc('effective_from')->get();

        // A component that already has an open (not ended) row cannot be assigned a second time.
        $openComponentIds = $employee->salaryComponents()->whereNull('effective_to')->pluck('salary_component_id');

        return view('salary.show', [
            'employee' => $employee,
            'lines' => $lines,
            'totals' => $this->salary->totals($employee, null, $lines),
            'history' => $history,
            'assignable' => SalaryComponent::active()->where('source', 'structure')->whereNotIn('id', $openComponentIds)->orderBy('type')->orderBy('name')->get(),
            'changes' => AuditLog::with('user')->where('module', 'employees')->where('action', 'salary_changed')
                ->where('record_id', $employee->id)->latest('id')->limit(10)->get(),
        ]);
    }

    /** Revise the basic salary. It applies from now on; payrolls already processed keep their own figures. */
    public function updateBasic(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'basic_salary.required' => 'Please enter the new basic salary.',
            'basic_salary.numeric' => 'The basic salary must be a number.',
            'basic_salary.min' => 'The basic salary cannot be negative.',
            'reason.required' => 'Please write the reason for the change (for example "annual increment").',
        ]);

        $old = (float) $employee->basic_salary;
        $new = (float) $data['basic_salary'];

        if (round($old, 2) === round($new, 2)) {
            throw new BusinessRuleException('The new basic salary is the same as the current one.');
        }

        $employee->update(['basic_salary' => $new]);

        AuditService::log(
            'salary_changed', 'employees',
            sprintf('Basic salary of %s changed from ৳%s to ৳%s. Reason: %s', $employee->employee_code, number_format($old, 2), number_format($new, 2), $data['reason']),
            $employee->id
        );

        return back()->with('success', 'The basic salary has been updated.');
    }

    public function assign(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'salary_component_id' => ['required', 'integer', 'exists:salary_components,id'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $employee->joining_date->toDateString()],
        ], [
            'salary_component_id.required' => 'Please choose a component.',
            'value.numeric' => 'The amount must be a number.',
            'effective_from.required' => 'Please choose the date it starts.',
            'effective_from.after_or_equal' => 'It cannot start before the employee joined.',
        ]);

        $component = SalaryComponent::findOrFail($data['salary_component_id']);

        if ($component->source !== 'structure' || $component->status !== 'active') {
            throw new BusinessRuleException('This component cannot be added to a salary structure.');
        }

        $this->checkPercent($component, $data['value'] ?? null);

        if ($employee->salaryComponents()->where('salary_component_id', $component->id)->whereNull('effective_to')->exists()) {
            throw new BusinessRuleException("{$component->name} is already part of this salary structure. Use \"Change amount\" instead.");
        }

        $row = $employee->salaryComponents()->create([
            'salary_component_id' => $component->id,
            'value' => $data['value'] ?? null,
            'effective_from' => $data['effective_from'],
        ]);

        AuditService::log('structure_added', 'salary', "{$component->name} added to the salary of {$employee->employee_code} from {$data['effective_from']}", $row->id);

        return back()->with('success', "{$component->name} has been added.");
    }

    /** Ends the current line the day before the new date and starts a new line, so history is kept. */
    public function revise(Request $request, Employee $employee, EmployeeSalaryComponent $employeeSalaryComponent)
    {
        $row = $this->ownedOpenRow($employee, $employeeSalaryComponent);

        $data = $request->validate([
            'value' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
        ], [
            'value.required' => 'Please enter the new amount.',
            'value.numeric' => 'The amount must be a number.',
            'effective_from.required' => 'Please choose the date the new amount starts.',
        ]);

        if (Carbon::parse($data['effective_from'])->lte($row->effective_from)) {
            throw new BusinessRuleException('The new amount must start after the date the current one started (' . $row->effective_from->format('d M Y') . ').');
        }

        $this->checkPercent($row->component, $data['value']);

        DB::transaction(function () use ($row, $data) {
            $row->update(['effective_to' => Carbon::parse($data['effective_from'])->subDay()->toDateString()]);

            EmployeeSalaryComponent::create([
                'employee_id' => $row->employee_id,
                'salary_component_id' => $row->salary_component_id,
                'value' => $data['value'],
                'effective_from' => $data['effective_from'],
            ]);
        });

        AuditService::log('structure_changed', 'salary', "{$row->component->name} for {$employee->employee_code} changed to {$data['value']} from {$data['effective_from']}", $row->id);

        return back()->with('success', "{$row->component->name} has been updated.");
    }

    public function end(Request $request, Employee $employee, EmployeeSalaryComponent $employeeSalaryComponent)
    {
        $row = $this->ownedOpenRow($employee, $employeeSalaryComponent);

        $data = $request->validate([
            'effective_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $row->effective_from->toDateString()],
        ], [
            'effective_to.required' => 'Please choose the last day it applies.',
            'effective_to.after_or_equal' => 'The last day cannot be before the day it started.',
        ]);

        $row->update(['effective_to' => $data['effective_to']]);

        AuditService::log('structure_ended', 'salary', "{$row->component->name} removed from the salary of {$employee->employee_code} after {$data['effective_to']}", $row->id);

        return back()->with('success', "{$row->component->name} has been removed from the salary structure.");
    }

    private function ownedOpenRow(Employee $employee, EmployeeSalaryComponent $row): EmployeeSalaryComponent
    {
        abort_unless($row->employee_id === $employee->id, 404);

        if ($row->effective_to !== null) {
            throw new BusinessRuleException('This line has already ended.');
        }

        return $row->load('component');
    }

    private function checkPercent(SalaryComponent $component, mixed $value): void
    {
        if ($component->calc_type === 'percent' && $value !== null && (float) $value > 100) {
            throw new BusinessRuleException("{$component->name} is a percentage, so the value cannot be more than 100.");
        }
    }
}
