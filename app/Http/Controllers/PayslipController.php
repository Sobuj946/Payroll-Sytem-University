<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\AuditService;
use App\Services\PayslipService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function __construct(private PayslipService $slips)
    {
    }

    // ------------------------------------------------------------------
    //  Payroll staff
    // ------------------------------------------------------------------

    public function index(Request $request)
    {
        $periods = PayrollPeriod::whereIn('status', PayslipService::FINAL_STATUSES)
            ->orderByDesc('year')->orderByDesc('month')->get();

        $period = $request->filled('period')
            ? $periods->firstWhere('id', (int) $request->period)
            : $periods->first();

        $payrolls = $period
            ? $period->payrolls()->with('employee.department', 'payments')
                ->when($request->filled('q'), function ($query) use ($request) {
                    $term = '%' . trim($request->q) . '%';
                    $query->whereHas('employee', function ($e) use ($term) {
                        $e->whereRaw("CONCAT(first_name, ' ', last_name) like ?", [$term])->orWhere('employee_code', 'like', $term);
                    });
                })
                ->orderBy('employee_id')->paginate(25)->withQueryString()
            : null;

        return view('payslips.index', compact('periods', 'period', 'payrolls'));
    }

    public function show(Payroll $payroll)
    {
        return $this->render($this->final($payroll), false);
    }

    public function pdf(Request $request, Payroll $payroll)
    {
        return $this->download([$this->slips->data($this->final($payroll))], $this->slips->fileName($payroll), $request, $payroll);
    }

    /** Every slip of a month in one PDF, one page each. */
    public function periodPdf(Request $request, PayrollPeriod $payrollPeriod)
    {
        abort_unless(in_array($payrollPeriod->status, PayslipService::FINAL_STATUSES, true), 404);

        $payrolls = $payrollPeriod->payrolls()->with('employee')->orderBy('employee_id')->get();
        abort_if($payrolls->isEmpty(), 404);

        $slips = $payrolls->map(fn ($payroll) => $this->slips->data($payroll))->all();

        AuditService::log('downloaded', 'payslips', "All salary slips for {$payrollPeriod->label} were downloaded", $payrollPeriod->id);

        return $this->pdfResponse($slips, sprintf('Payslips-%d-%02d.pdf', $payrollPeriod->year, $payrollPeriod->month));
    }

    // ------------------------------------------------------------------
    //  The signed-in employee
    // ------------------------------------------------------------------

    public function mine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $payrolls = Payroll::with('period', 'payments')
            ->where('employee_id', $employee->id)
            ->whereHas('period', fn ($q) => $q->whereIn('status', PayslipService::FINAL_STATUSES))
            ->get()
            ->sortByDesc(fn ($p) => $p->period->year * 100 + $p->period->month)
            ->values();

        return view('payslips.mine', compact('employee', 'payrolls'));
    }

    public function mineShow(Request $request, Payroll $payroll)
    {
        return $this->render($this->final($this->own($request, $payroll)), true);
    }

    public function minePdf(Request $request, Payroll $payroll)
    {
        $payroll = $this->final($this->own($request, $payroll));

        return $this->download([$this->slips->data($payroll)], $this->slips->fileName($payroll), $request, $payroll);
    }

    // ------------------------------------------------------------------

    private function render(Payroll $payroll, bool $mine)
    {
        return view('payslips.show', [
            'slip' => $this->slips->data($payroll),
            'cur' => '৳',
            'mine' => $mine,
            'canPdf' => $mine || auth()->user()->hasPermission('payslips.generate'),
            'pdfUrl' => $mine ? route('my.payslips.pdf', $payroll) : route('payslips.pdf', $payroll),
            'backUrl' => $mine ? route('my.payslips') : route('payslips.index', ['period' => $payroll->payroll_period_id]),
        ]);
    }

    private function download(array $slips, string $fileName, Request $request, Payroll $payroll)
    {
        AuditService::log('downloaded', 'payslips', "Salary slip of {$payroll->employee->employee_code} for {$payroll->period->label} was downloaded", $payroll->id);

        return $this->pdfResponse($slips, $fileName);
    }

    private function pdfResponse(array $slips, string $fileName)
    {
        return Pdf::loadView('payslips.pdf', ['slips' => $slips, 'cur' => 'BDT '])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->download($fileName);
    }

    /** A slip only exists for an approved, paid or closed payroll. */
    private function final(Payroll $payroll): Payroll
    {
        $payroll->loadMissing('period', 'employee');
        abort_unless(in_array($payroll->period->status, PayslipService::FINAL_STATUSES, true), 404);

        return $payroll;
    }

    private function own(Request $request, Payroll $payroll): Payroll
    {
        abort_unless($request->user()->employee_id && $payroll->employee_id === $request->user()->employee_id, 403);

        return $payroll;
    }
}
