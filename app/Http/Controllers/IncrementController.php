<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AuditService;
use App\Services\IncrementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncrementController extends Controller
{
    public function __construct(private IncrementService $increments)
    {
    }

    public function index(Request $request)
    {
        $employees = Employee::with('payGrade', 'department', 'designation')
            ->whereNotNull('pay_grade_id')
            ->where('status', 'active')
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('designation', fn ($d) => $d->where('category', $request->category)))
            ->orderBy('employee_code')->get();

        $rows = $employees->map(fn ($e) => ['employee' => $e, 'proposal' => $this->increments->proposal($e)]);

        return view('salary.increments', [
            'rows' => $rows,
            'totalGain' => $rows->filter(fn ($row) => $row['proposal']['eligible'])->sum(fn ($row) => $row['proposal']['increment']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Applies the increment to the ticked employees. The amounts are worked out again here, not taken from the form. */
    public function apply(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:employees,id'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'ids.required' => 'Please tick the employees who should get the increment.',
            'reason.required' => 'Please write a reason, for example "Annual increment July 2027".',
        ]);

        $applied = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, &$applied, &$skipped) {
            foreach ($data['ids'] as $id) {
                $employee = Employee::with('payGrade')->whereKey($id)->lockForUpdate()->first();
                $proposal = $employee ? $this->increments->proposal($employee) : null;

                if (! $proposal || ! $proposal['eligible']) {
                    $skipped++;
                    continue;
                }

                $old = (float) $employee->basic_salary;
                $employee->update(['basic_salary' => $proposal['proposed'], 'last_increment_on' => today()]);

                AuditService::log(
                    'salary_changed', 'employees',
                    sprintf('Basic salary of %s changed from ৳%s to ৳%s. Reason: %s', $employee->employee_code, number_format($old, 2), number_format($proposal['proposed'], 2), $data['reason']),
                    $employee->id
                );
                $applied++;
            }
        });

        if ($applied === 0) {
            throw new BusinessRuleException('None of the ticked employees can get an increment now (they may have had one in the last 12 months or are at the top of their grade).');
        }

        AuditService::log('increment', 'salary', "Annual increment applied to {$applied} employee(s). Reason: {$data['reason']}");

        return back()->with('success', "Increment applied to {$applied} employee(s)." . ($skipped ? " {$skipped} could not be given one and were left unchanged." : ''));
    }
}
