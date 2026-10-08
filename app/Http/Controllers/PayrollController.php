<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll)
    {
    }

    /** All payroll periods, newest first. */
    public function index()
    {
        $periods = PayrollPeriod::withCount('payrolls')
            ->withSum('payrolls', 'gross_salary')
            ->withSum('payrolls', 'total_deductions')
            ->withSum('payrolls', 'net_salary')
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate(12);

        return view('payroll.index', compact('periods'));
    }

    public function show(Request $request, PayrollPeriod $payrollPeriod)
    {
        $payrolls = $payrollPeriod->payrolls()->with('employee.department')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . trim($request->q) . '%';
                $query->whereHas('employee', function ($e) use ($term) {
                    $e->whereRaw("CONCAT(first_name, ' ', last_name) like ?", [$term])->orWhere('employee_code', 'like', $term);
                });
            })
            ->orderBy('employee_id')
            ->paginate(25)
            ->withQueryString();

        return view('payroll.show', [
            'period' => $payrollPeriod->load('creator', 'approver'),
            'payrolls' => $payrolls,
            'summary' => $this->payroll->summary($payrollPeriod),
            // The attendance check only matters while the payroll can still be changed.
            'gaps' => in_array($payrollPeriod->status, ['draft', 'processed'], true)
                ? $this->payroll->attendanceGaps($payrollPeriod) : collect(),
            'adjustmentCount' => $payrollPeriod->adjustments()->count(),
        ]);
    }

    /** One employee's payroll with every earning and deduction line. */
    public function record(Payroll $payroll)
    {
        $payroll->load('period', 'employee.department', 'employee.designation', 'items', 'payments');

        return view('payroll.record', [
            'payroll' => $payroll,
            'earnings' => $payroll->items->where('type', 'earning')->values(),
            'deductions' => $payroll->items->where('type', 'deduction')->values(),
        ]);
    }

    public function process(Request $request, PayrollPeriod $payrollPeriod)
    {
        $summary = $this->payroll->process($payrollPeriod, $request->user());

        return redirect()->route('payroll.show', $payrollPeriod)->with(
            'success',
            sprintf('Payroll for %s has been processed for %d employees. Total net salary: ৳%s.', $payrollPeriod->label, $summary['count'], number_format($summary['net'], 2))
        );
    }

    public function review(Request $request, PayrollPeriod $payrollPeriod)
    {
        $this->payroll->markReviewed($payrollPeriod, $request->user());

        return back()->with('success', "Payroll for {$payrollPeriod->label} has been marked as reviewed.");
    }

    public function approve(Request $request, PayrollPeriod $payrollPeriod)
    {
        $this->payroll->approve($payrollPeriod, $request->user());

        return back()->with('success', "Payroll for {$payrollPeriod->label} has been approved and is now locked.");
    }

    public function close(Request $request, PayrollPeriod $payrollPeriod)
    {
        $this->payroll->close($payrollPeriod, $request->user());

        return back()->with('success', "Payroll for {$payrollPeriod->label} has been closed.");
    }

    public function reopen(Request $request, PayrollPeriod $payrollPeriod)
    {
        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:5', 'max:255']],
            [
                'reason.required' => 'Please write why this payroll is being reopened.',
                'reason.min' => 'Please give a little more detail in the reason.',
            ]
        );

        $this->payroll->reopen($payrollPeriod, $request->user(), $data['reason']);

        return redirect()->route('payroll.show', $payrollPeriod)
            ->with('success', "Payroll for {$payrollPeriod->label} has been reopened as Draft. Make the corrections and process it again.");
    }
}
