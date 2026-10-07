<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads an employee's salary structure. The payroll engine (Phase 9) uses the same methods,
 * so what HR sees on the salary page is what payroll will calculate.
 *
 * Percentage components are always a percentage of the BASIC salary.
 */
class SalaryService
{
    /**
     * Components in effect on a date (today by default), earnings first.
     * Each line: row, component, name, type, rate, is_percent, basis, amount, active.
     */
    public function structure(Employee $employee, ?Carbon $asOf = null): Collection
    {
        $date = ($asOf ?? today())->toDateString();
        $basic = (float) $employee->basic_salary;

        return $employee->salaryComponents()->with('component')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->get()
            ->sortBy(fn ($row) => ($row->component->type === 'earning' ? '0' : '1') . $row->component->name)
            ->values()
            ->map(function ($row) use ($basic) {
                $component = $row->component;
                $rate = (float) ($row->value ?? $component->default_value);
                $isPercent = $component->calc_type === 'percent';

                return [
                    'row' => $row,
                    'component' => $component,
                    'name' => $component->name,
                    'type' => $component->type,
                    'rate' => $rate,
                    'is_percent' => $isPercent,
                    'basis' => $isPercent ? rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '% of basic' : 'Fixed',
                    'amount' => $isPercent ? round($basic * $rate / 100, 2) : round($rate, 2),
                    'active' => $component->status === 'active',
                ];
            });
    }

    /** Fixed monthly figures before attendance, overtime, bonus and tax. Inactive components are ignored. */
    public function totals(Employee $employee, ?Carbon $asOf = null, ?Collection $lines = null): array
    {
        $lines = ($lines ?? $this->structure($employee, $asOf))->where('active', true);

        $basic = (float) $employee->basic_salary;
        $allowances = (float) $lines->where('type', 'earning')->sum('amount');
        $deductions = (float) $lines->where('type', 'deduction')->sum('amount');

        return [
            'basic' => $basic,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'gross' => $basic + $allowances,
            'net' => $basic + $allowances - $deductions,
        ];
    }
}
