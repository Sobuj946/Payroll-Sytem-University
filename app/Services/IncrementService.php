<?php

namespace App\Services;

use App\Models\Employee;

/**
 * Annual increment. The percentage belongs to the employee's pay grade (set by the administrator),
 * it is taken from the current basic salary, rounded to the nearest taka, and the new basic salary
 * never goes above the highest basic of the grade. One increment is allowed per 12 months.
 */
class IncrementService
{
    /** @return array{eligible: bool, increment: float, proposed: float, note: string} */
    public function proposal(Employee $employee): array
    {
        $grade = $employee->payGrade;
        $basic = (float) $employee->basic_salary;

        if (! $grade) {
            return ['eligible' => false, 'increment' => 0.0, 'proposed' => $basic, 'note' => 'No pay grade'];
        }

        $raise = round($basic * (float) $grade->increment_percent / 100);
        $proposed = min($grade->ceiling, $basic + $raise);
        $increment = round($proposed - $basic, 2);

        if ($increment <= 0) {
            return ['eligible' => false, 'increment' => 0.0, 'proposed' => $basic, 'note' => 'At the top of the grade'];
        }

        if ($employee->last_increment_on && $employee->last_increment_on->gt(today()->subYear())) {
            return ['eligible' => false, 'increment' => $increment, 'proposed' => $proposed, 'note' => 'Increment given on ' . $employee->last_increment_on->format('d M Y')];
        }

        return ['eligible' => true, 'increment' => $increment, 'proposed' => $proposed, 'note' => ''];
    }
}
