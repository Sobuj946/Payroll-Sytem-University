<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Month-by-month items that are not part of the fixed structure:
 * overtime, bonus, loan instalment, salary advance and other deductions.
 */
class PayrollAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $month = $this->parseMonth($request->query('month'));
        $period = PayrollPeriod::where('year', $month->year)->where('month', $month->month)->first();

        return view('salary.adjustments', [
            'month' => $month,
            'period' => $period,
            'adjustments' => $period
                ? $period->adjustments()->with('employee', 'component')->orderBy('employee_id')->orderBy('id')->get()
                : collect(),
            'employees' => $period
                ? Employee::where('status', 'active')->whereDate('joining_date', '<=', $period->end_date->toDateString())->orderBy('employee_code')->get()
                : collect(),
            'components' => SalaryComponent::active()->where('source', 'adjustment')->orderBy('type')->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'canEdit' => $period && in_array($period->status, ['draft', 'processed'], true) && auth()->user()->hasPermission('payroll.process'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payroll_period_id' => ['required', 'integer', 'exists:payroll_periods,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'salary_component_id' => ['required', 'integer', Rule::exists('salary_components', 'id')->where('source', 'adjustment')->where('status', 'active')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'employee_id.required' => 'Please choose an employee.',
            'salary_component_id.required' => 'Please choose what you are adding (overtime, bonus ...).',
            'salary_component_id.exists' => 'The selected item is not available.',
            'amount.required' => 'Please enter the amount.',
            'amount.numeric' => 'The amount must be a number.',
            'amount.gt' => 'The amount must be more than zero.',
        ]);

        $period = PayrollPeriod::findOrFail($data['payroll_period_id']);
        $this->ensureEditable($period);

        $employee = Employee::findOrFail($data['employee_id']);

        if ($employee->joining_date->gt($period->end_date)) {
            throw new BusinessRuleException("{$employee->full_name} had not joined yet in {$period->label}.");
        }

        $component = SalaryComponent::findOrFail($data['salary_component_id']);

        $adjustment = PayrollAdjustment::create($data + ['created_by' => $request->user()->id]);

        AuditService::log(
            'adjustment_added', 'payroll',
            sprintf('%s of ৳%s added for %s in %s', $component->name, number_format((float) $data['amount'], 2), $employee->employee_code, $period->label),
            $adjustment->id
        );

        return back()->with('success', "{$component->name} has been added for {$employee->full_name}.");
    }

    /**
     * Gives the same item to many employees at once (for example the festival bonus of 100% of basic salary).
     * Employees who already have this item in the month are skipped, so pressing the button twice is harmless.
     */
    public function storeForAll(Request $request)
    {
        $data = $request->validate([
            'payroll_period_id' => ['required', 'integer', 'exists:payroll_periods,id'],
            'salary_component_id' => ['required', 'integer', Rule::exists('salary_components', 'id')->where('source', 'adjustment')->where('status', 'active')],
            'basis' => ['required', Rule::in(['percent_of_basic', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'category' => ['nullable', Rule::in(array_keys(Designation::CATEGORIES))],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'salary_component_id.required' => 'Please choose what you are adding.',
            'basis.required' => 'Please choose how the amount is worked out.',
            'value.required' => 'Please enter the percentage or the amount.',
            'value.gt' => 'The value must be more than zero.',
        ]);

        if ($data['basis'] === 'percent_of_basic' && (float) $data['value'] > 500) {
            throw new BusinessRuleException('A percentage of basic salary cannot be more than 500.');
        }

        $period = PayrollPeriod::findOrFail($data['payroll_period_id']);
        $this->ensureEditable($period);
        $component = SalaryComponent::findOrFail($data['salary_component_id']);

        $employees = Employee::where('status', 'active')
            ->whereDate('joining_date', '<=', $period->end_date->toDateString())
            ->when($data['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($data['category'] ?? null, fn ($q, $category) => $q->whereHas('designation', fn ($d) => $d->where('category', $category)))
            ->get();

        $already = PayrollAdjustment::where('payroll_period_id', $period->id)
            ->where('salary_component_id', $component->id)->pluck('employee_id')->flip();

        $added = 0;

        DB::transaction(function () use ($employees, $already, $data, $period, $request, &$added) {
            foreach ($employees as $employee) {
                if (isset($already[$employee->id])) {
                    continue;
                }

                $amount = $data['basis'] === 'percent_of_basic'
                    ? round((float) $employee->basic_salary * (float) $data['value'] / 100, 2)
                    : round((float) $data['value'], 2);

                if ($amount <= 0) {
                    continue;
                }

                PayrollAdjustment::create([
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'salary_component_id' => $data['salary_component_id'],
                    'amount' => $amount,
                    'note' => $data['note'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
                $added++;
            }
        });

        if ($added === 0) {
            throw new BusinessRuleException('Nobody was added: everyone who matches already has this item for the month, or nobody matches the choice.');
        }

        $how = $data['basis'] === 'percent_of_basic' ? $data['value'] . '% of basic salary' : '৳' . number_format((float) $data['value'], 2) . ' each';
        AuditService::log('adjustment_added', 'payroll', "{$component->name} ({$how}) was added for {$added} employees in {$period->label}", $period->id);

        return back()->with('success', "{$component->name} has been added for {$added} employees.");
    }

    public function destroy(PayrollAdjustment $adjustment)
    {
        $adjustment->load('period', 'employee', 'component');
        $this->ensureEditable($adjustment->period);

        $adjustment->delete();

        AuditService::log(
            'adjustment_removed', 'payroll',
            "{$adjustment->component->name} of ৳" . number_format((float) $adjustment->amount, 2) . " was removed for {$adjustment->employee->employee_code} in {$adjustment->period->label}",
            $adjustment->id
        );

        return back()->with('success', 'The item has been removed.');
    }

    /** Items can change while payroll is Draft, or Processed (then process it again). Reviewed payroll is locked. */
    private function ensureEditable(PayrollPeriod $period): void
    {
        if (! in_array($period->status, ['draft', 'processed'], true)) {
            throw new BusinessRuleException("Payroll for {$period->label} is already {$period->status}, so items can no longer be changed.");
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
