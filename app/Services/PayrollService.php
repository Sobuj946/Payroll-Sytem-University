<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\Setting;
use App\Models\TaxSlab;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Monthly payroll: calculation and the Draft -> ... -> Closed workflow.
 *
 * Rules that can change live in the database, not here:
 *  - weekly off days and holidays        (Settings: weekly_off_days, holidays table)
 *  - daily rate divisor                  (Settings: working_day_basis, fixed_working_days)
 *  - what the daily rate is based on     (Settings: deduction_base = gross | basic)
 *  - late arrivals that cost one day     (Settings: late_days_per_deduction, 0 = switched off)
 *  - income tax                          (tax_slabs table, annual slabs)
 *  - allowances and deductions           (salary components and each employee's structure)
 */
class PayrollService
{
    /** Employees with these statuses are paid. Suspended, resigned and terminated staff are not. */
    private const PAID_STATUSES = ['active', 'on_leave'];

    public function __construct(
        private SalaryService $salary,
        private AttendanceService $attendance,
        private LeaveService $leave,
    ) {
    }

    // ------------------------------------------------------------------
    //  Processing
    // ------------------------------------------------------------------

    /**
     * Calculates payroll for every eligible employee in ONE database transaction.
     * If anything fails, nothing is saved. Running it again replaces the earlier result.
     */
    public function process(PayrollPeriod $period, User $by): array
    {
        return DB::transaction(function () use ($period, $by) {
            $locked = $this->lock($period, ['draft', 'processed'], 'Payroll can only be processed while the period is Draft or Processed.');

            $locked->update(['status' => 'processing']);

            // A re-run replaces the previous result (items are removed with their payroll rows).
            Payroll::where('payroll_period_id', $locked->id)->delete();

            $employees = $this->eligibleEmployees($locked);

            if ($employees->isEmpty()) {
                throw new BusinessRuleException("There are no employees to pay for {$locked->label}.");
            }

            $context = $this->context($locked);
            $adjustments = $locked->adjustments()->with('component')->get()->groupBy('employee_id');

            foreach ($employees as $employee) {
                $this->buildPayroll($locked, $employee, $adjustments->get($employee->id, collect()), $context);
            }

            $locked->update(['status' => 'processed']);

            $summary = $this->summary($locked);

            AuditService::log(
                'processed', 'payroll',
                sprintf('Payroll for %s processed for %d employees (net ৳%s)', $locked->label, $summary['count'], number_format($summary['net'], 2)),
                $locked->id, $by->id
            );

            return $summary;
        });
    }

    /** Totals for a period, read from the saved payroll rows. */
    public function summary(PayrollPeriod $period): array
    {
        $row = Payroll::where('payroll_period_id', $period->id)
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(gross_salary),0) as g, COALESCE(SUM(total_deductions),0) as d, COALESCE(SUM(net_salary),0) as n, COALESCE(SUM(tax),0) as t')
            ->first();

        return [
            'count' => (int) $row->c,
            'gross' => (float) $row->g,
            'deductions' => (float) $row->d,
            'net' => (float) $row->n,
            'tax' => (float) $row->t,
        ];
    }

    // ------------------------------------------------------------------
    //  Workflow
    // ------------------------------------------------------------------

    public function markReviewed(PayrollPeriod $period, User $by): void
    {
        DB::transaction(function () use ($period, $by) {
            $locked = $this->lock($period, ['processed'], 'Only processed payroll can be marked as reviewed.');
            $locked->update(['status' => 'reviewed']);
            AuditService::log('reviewed', 'payroll', "Payroll for {$locked->label} was reviewed", $locked->id, $by->id);
        });
    }

    public function approve(PayrollPeriod $period, User $by): void
    {
        DB::transaction(function () use ($period, $by) {
            $locked = $this->lock($period, ['reviewed'], 'Only reviewed payroll can be approved.');

            $summary = $this->summary($locked);
            if ($summary['count'] === 0) {
                throw new BusinessRuleException('There is no payroll to approve.');
            }

            $locked->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()]);

            AuditService::log(
                'approved', 'payroll',
                sprintf('Payroll for %s was approved (%d employees, net ৳%s)', $locked->label, $summary['count'], number_format($summary['net'], 2)),
                $locked->id, $by->id
            );
        });
    }

    public function close(PayrollPeriod $period, User $by): void
    {
        DB::transaction(function () use ($period, $by) {
            $locked = $this->lock($period, ['paid'], 'Only a fully paid period can be closed.');
            $locked->update(['status' => 'closed']);
            AuditService::log('closed', 'payroll', "Payroll for {$locked->label} was closed", $locked->id, $by->id);
        });
    }

    /**
     * Controlled correction: takes a processed, reviewed or approved period back to Draft.
     * A reason is required and the old totals are written to the audit log.
     * Refused once any salary has been paid.
     */
    public function reopen(PayrollPeriod $period, User $by, string $reason): void
    {
        DB::transaction(function () use ($period, $by, $reason) {
            $locked = $this->lock($period, ['processed', 'reviewed', 'approved'], 'Only processed, reviewed or approved payroll can be reopened.');

            $payrollIds = Payroll::where('payroll_period_id', $locked->id)->pluck('id');

            if (Payment::whereIn('payroll_id', $payrollIds)->where('status', 'paid')->exists()) {
                throw new BusinessRuleException('Some salaries of this period are already paid, so it cannot be reopened.');
            }

            Payment::whereIn('payroll_id', $payrollIds)->delete(); // unpaid payment lines only

            $before = $this->summary($locked);
            $previous = $locked->status;

            $locked->update([
                'status' => 'draft',
                'approved_by' => null,
                'approved_at' => null,
                'notes' => $reason,
            ]);

            AuditService::log(
                'reopened', 'payroll',
                sprintf('Payroll for %s was reopened from %s (earlier net ৳%s). Reason: %s', $locked->label, $previous, number_format($before['net'], 2), $reason),
                $locked->id, $by->id
            );
        });
    }

    // ------------------------------------------------------------------
    //  Pre-check shown on the period page
    // ------------------------------------------------------------------

    /**
     * Employees with working days that have no attendance record and no approved leave.
     * Payroll treats an unrecorded day as a normal working day, so these should be fixed first.
     */
    public function attendanceGaps(PayrollPeriod $period): Collection
    {
        $context = $this->context($period);
        $today = today()->toDateString();
        $gaps = collect();

        foreach ($this->eligibleEmployees($period) as $employee) {
            $joined = $employee->joining_date->toDateString();
            $dates = $context['workingDates']->filter(fn ($d) => $d >= $joined && $d <= $today);

            $recorded = Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
                ->get()->map(fn ($r) => $r->date->format('Y-m-d'))->all();

            $onLeave = [];
            $leaves = LeaveRequest::where('employee_id', $employee->id)->where('status', 'approved')
                ->whereDate('start_date', '<=', $period->end_date->toDateString())
                ->whereDate('end_date', '>=', $period->start_date->toDateString())->get();
            foreach ($leaves as $leave) {
                foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $day) {
                    $onLeave[] = $day->format('Y-m-d');
                }
            }

            $missing = $dates->reject(fn ($d) => in_array($d, $recorded, true) || in_array($d, $onLeave, true))->count();

            if ($missing > 0) {
                $gaps->push(['employee' => $employee, 'missing' => $missing]);
            }
        }

        return $gaps->sortByDesc('missing')->values();
    }

    // ------------------------------------------------------------------
    //  Calculation
    // ------------------------------------------------------------------

    private function eligibleEmployees(PayrollPeriod $period): Collection
    {
        return Employee::with('department')
            ->whereIn('status', self::PAID_STATUSES)
            ->whereDate('joining_date', '<=', $period->end_date->toDateString())
            ->orderBy('employee_code')
            ->get();
    }

    /** Values shared by every employee of the period. */
    private function context(PayrollPeriod $period): array
    {
        $off = $this->attendance->offDaysFor($period->start_date, $period->end_date);

        $workingDates = collect(CarbonPeriod::create($period->start_date, $period->end_date))
            ->map(fn ($day) => $day->format('Y-m-d'))
            ->reject(fn ($date) => isset($off[$date]))
            ->values();

        $divisor = Setting::get('working_day_basis', 'calendar_working_days') === 'fixed'
            ? max(1, (int) Setting::get('fixed_working_days', 30))
            : max(1, $workingDates->count());

        $effective = TaxSlab::whereDate('effective_from', '<=', $period->end_date->toDateString())->max('effective_from');
        $slabs = $effective
            ? TaxSlab::whereDate('effective_from', $effective)->orderBy('from_amount')->get()
            : collect();

        return [
            'workingDates' => $workingDates,
            'divisor' => $divisor,
            'slabs' => $slabs,
            'systemIds' => SalaryComponent::whereIn('code', ['TAX', 'LATE', 'ABS', 'UNPAID'])->pluck('id', 'code'),
            'deductionBase' => Setting::get('deduction_base', 'gross') === 'basic' ? 'basic' : 'gross',
            'lateDaysPerDeduction' => max(0, (int) Setting::get('late_days_per_deduction', 0)),
        ];
    }

    private function buildPayroll(PayrollPeriod $period, Employee $employee, Collection $adjustments, array $ctx): void
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $joined = $employee->joining_date->toDateString();

        // Working days after the joining date; earlier ones are not payable.
        $employedDates = $ctx['workingDates']->filter(fn ($d) => $d >= $joined)->values();
        $preJoiningDays = $ctx['workingDates']->count() - $employedDates->count();

        // ----- attendance and leave -----
        $records = Attendance::where('employee_id', $employee->id)->whereBetween('date', [$start, $end])->get()
            ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        [$unpaidLeaveDays, $unpaidLeaveDates] = $this->unpaidLeave($employee, $period, $employedDates);

        $absent = 0.0;
        $halfDays = 0;
        $lateCount = 0;
        $presentDays = 0.0;

        foreach ($employedDates as $date) {
            $record = $records->get($date);

            // Days covered by approved unpaid leave are charged once, as leave.
            if (! $record || in_array($date, $unpaidLeaveDates, true)) {
                continue;
            }

            switch ($record->status) {
                case 'absent':
                    $absent += 1;
                    break;
                case 'half_day':
                    $halfDays++;
                    $presentDays += 0.5;
                    break;
                case 'late':
                    $lateCount++;
                    $presentDays += 1;
                    break;
                case 'present':
                    $presentDays += 1;
                    break;
            }
        }

        $absenceDays = $absent + 0.5 * $halfDays;
        $lateDeductionDays = $ctx['lateDaysPerDeduction'] > 0 ? intdiv($lateCount, $ctx['lateDaysPerDeduction']) : 0;

        // ----- earnings -----
        $items = [];
        $add = function (string $name, string $type, float $amount, ?int $componentId = null, bool $always = false) use (&$items) {
            $amount = round($amount, 2);
            if ($amount > 0 || $always) {
                $items[] = ['salary_component_id' => $componentId, 'name' => $name, 'type' => $type, 'amount' => $amount];
            }
        };

        $basic = round((float) $employee->basic_salary, 2);
        $add('Basic Salary', 'earning', $basic, null, true);
        $taxable = $basic;

        $structure = $this->salary->structure($employee, $period->end_date)->where('active', true);

        $allowances = 0.0;
        foreach ($structure->where('type', 'earning') as $line) {
            $add($line['name'], 'earning', $line['amount'], $line['component']->id);
            $allowances += round($line['amount'], 2);
            if ($line['component']->is_taxable) {
                $taxable += round($line['amount'], 2);
            }
        }

        $overtime = 0.0;
        $bonus = 0.0;
        foreach ($adjustments->filter(fn ($a) => $a->component->type === 'earning') as $adjustment) {
            $amount = round((float) $adjustment->amount, 2);
            $add($adjustment->component->name, 'earning', $amount, $adjustment->component->id);
            // Overtime has its own column, every other monthly earning is counted as bonus.
            if ($adjustment->component->code === 'OT') {
                $overtime += $amount;
            } else {
                $bonus += $amount;
            }
            if ($adjustment->component->is_taxable) {
                $taxable += $amount;
            }
        }

        $gross = round($basic + $allowances + $overtime + $bonus, 2);

        // ----- deductions -----
        foreach ($structure->where('type', 'deduction') as $line) {
            $add($line['name'], 'deduction', $line['amount'], $line['component']->id);
        }
        foreach ($adjustments->filter(fn ($a) => $a->component->type === 'deduction') as $adjustment) {
            $add($adjustment->component->name, 'deduction', (float) $adjustment->amount, $adjustment->component->id);
        }

        // Daily rate = (basic + fixed allowances, or basic only) / working days of the month.
        $rateBase = $ctx['deductionBase'] === 'basic' ? $basic : $basic + $allowances;
        $dailyRate = $rateBase / $ctx['divisor'];

        $add("Absence Deduction ({$this->days($absenceDays)})", 'deduction', $dailyRate * $absenceDays, $ctx['systemIds']->get('ABS'));
        $add("Unpaid Leave Deduction ({$this->days($unpaidLeaveDays)})", 'deduction', $dailyRate * $unpaidLeaveDays, $ctx['systemIds']->get('UNPAID'));
        $add("Late Deduction ({$this->days($lateDeductionDays)})", 'deduction', $dailyRate * $lateDeductionDays, $ctx['systemIds']->get('LATE'));
        $add("Days before joining ({$this->days($preJoiningDays)})", 'deduction', $dailyRate * $preJoiningDays, null);

        $tax = $this->monthlyTax($taxable, $ctx['slabs']);
        $add('Income Tax', 'deduction', $tax, $ctx['systemIds']->get('TAX'));

        // Totals come from the saved lines, so a payslip always adds up exactly.
        $totalDeductions = round(collect($items)->where('type', 'deduction')->sum('amount'), 2);
        $net = round($gross - $totalDeductions, 2);

        if ($net < 0) {
            throw new BusinessRuleException(sprintf(
                'The net salary of %s (%s) would be negative (৳%s). Please review their deductions and process again.',
                $employee->full_name, $employee->employee_code, number_format($net, 2)
            ));
        }

        $payroll = Payroll::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'basic_salary' => $basic,
            'total_allowances' => round($allowances, 2),
            'overtime' => round($overtime, 2),
            'bonus' => round($bonus, 2),
            'gross_salary' => $gross,
            'tax' => $tax,
            'total_deductions' => $totalDeductions,
            'net_salary' => $net,
            'working_days' => $ctx['workingDates']->count(),
            'present_days' => $presentDays,
            'absent_days' => $absenceDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'late_count' => min($lateCount, 255),
        ]);

        $payroll->items()->createMany($items);
    }

    /**
     * Approved UNPAID leave inside the period: [days, dates].
     * Weekly offs, holidays and days before joining are skipped because $employedDates only holds payable working days.
     */
    private function unpaidLeave(Employee $employee, PayrollPeriod $period, Collection $employedDates): array
    {
        $requests = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)->where('status', 'approved')
            ->whereDate('start_date', '<=', $period->end_date->toDateString())
            ->whereDate('end_date', '>=', $period->start_date->toDateString())
            ->get()
            ->filter(fn ($request) => ! $request->leaveType->is_paid);

        $dates = [];
        $days = 0.0;

        foreach ($requests as $request) {
            $isHalfDay = (float) $request->days === 0.5 && $request->start_date->isSameDay($request->end_date);

            foreach (CarbonPeriod::create($request->start_date, $request->end_date) as $day) {
                $date = $day->format('Y-m-d');

                if (! $employedDates->contains($date) || in_array($date, $dates, true)) {
                    continue;
                }

                $dates[] = $date;
                $days += $isHalfDay ? 0.5 : 1;
            }
        }

        return [$days, $dates];
    }

    /**
     * Income tax for one month from annual slabs: the monthly taxable income is projected
     * to a year, taxed slice by slice, and divided by 12. The slabs are data, not code.
     */
    private function monthlyTax(float $monthlyTaxable, Collection $slabs): float
    {
        $annual = $monthlyTaxable * 12;
        $tax = 0.0;

        foreach ($slabs as $slab) {
            $from = (float) $slab->from_amount;
            $to = $slab->to_amount === null ? INF : (float) $slab->to_amount;

            if ($annual > $from) {
                $tax += (min($annual, $to) - $from) * ((float) $slab->rate_percent / 100);
            }
        }

        return round($tax / 12, 2);
    }

    private function days(float|int $value): string
    {
        $text = rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');

        return $text . ($text === '1' ? ' day' : ' days');
    }

    /** Locks the period row and checks its status. */
    private function lock(PayrollPeriod $period, array $allowed, string $message): PayrollPeriod
    {
        $locked = PayrollPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();

        if (! in_array($locked->status, $allowed, true)) {
            throw new BusinessRuleException($message . " (It is currently {$locked->status}.)");
        }

        return $locked;
    }
}
