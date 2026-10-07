<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Payroll periods. Phase 9 adds the list, processing and approval workflow to this controller. */
class PayrollPeriodController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'month' => [
                'required', 'date_format:Y-m',
                'after_or_equal:' . now()->subMonths(12)->format('Y-m'),
                'before_or_equal:' . now()->addMonth()->format('Y-m'),
            ],
        ], [
            'month.required' => 'Please choose a month.',
            'month.date_format' => 'Please choose a valid month.',
            'month.after_or_equal' => 'Payroll periods can only be created for the last 12 months.',
            'month.before_or_equal' => 'Payroll periods can only be created up to next month.',
        ]);

        $start = Carbon::createFromFormat('!Y-m', $data['month'])->startOfMonth();

        $period = PayrollPeriod::firstOrCreate(
            ['year' => $start->year, 'month' => $start->month],
            [
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->endOfMonth()->toDateString(),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]
        );

        if ($period->wasRecentlyCreated) {
            AuditService::log('period_created', 'payroll', "Payroll period {$period->label} was created", $period->id);
        }

        return redirect()->route('salary.adjustments.index', ['month' => $start->format('Y-m')])
            ->with('success', $period->wasRecentlyCreated ? "Payroll period {$period->label} has been created." : "Payroll period {$period->label} already exists.");
    }
}
