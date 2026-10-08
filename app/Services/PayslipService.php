<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\Setting;

/** Collects everything a salary slip shows, so the screen, print and PDF versions always match. */
class PayslipService
{
    /** Slips exist once the payroll is approved. */
    public const FINAL_STATUSES = ['approved', 'paid', 'closed'];

    public function data(Payroll $payroll): array
    {
        $payroll->loadMissing('period', 'employee.department', 'employee.designation', 'items', 'payments');

        $earnings = $payroll->items->where('type', 'earning')->values();
        $deductions = $payroll->items->where('type', 'deduction')->values();

        // One table row holds an earning on the left and a deduction on the right.
        $rows = [];
        foreach (range(0, max($earnings->count(), $deductions->count()) - 1) as $i) {
            $rows[] = [$earnings->get($i), $deductions->get($i)];
        }

        $employee = $payroll->employee;
        $period = $payroll->period;

        return [
            'company' => $this->company(),
            'payroll' => $payroll,
            'employee' => $employee,
            'period' => $period,
            'slipNo' => sprintf('PS-%d%02d-%s', $period->year, $period->month, $employee->employee_code),
            'rows' => $rows,
            'payment' => $payroll->payments->where('status', '!=', 'cancelled')->sortByDesc('id')->first(),
            'account' => $this->mask($employee->bank_account_number),
            'generatedAt' => now(),
        ];
    }

    public function company(): array
    {
        return [
            'name' => Setting::get('company_name', config('app.name')),
            'address' => Setting::get('company_address'),
            'phone' => Setting::get('company_phone'),
            'email' => Setting::get('company_email'),
            'logo' => $this->logoDataUri(),
        ];
    }

    public function fileName(Payroll $payroll): string
    {
        $payroll->loadMissing('period', 'employee');

        return sprintf('Payslip-%s-%d-%02d.pdf', $payroll->employee->employee_code, $payroll->period->year, $payroll->period->month);
    }

    /** Only the last four digits of an account number are printed. */
    private function mask(?string $account): ?string
    {
        if (! $account) {
            return null;
        }

        return str_repeat('*', max(0, strlen($account) - 4)) . substr($account, -4);
    }

    /**
     * The logo file name is saved in Settings (company_logo) and the file sits in public/uploads.
     * It is embedded in the page so the PDF does not need to fetch anything.
     */
    private function logoDataUri(): ?string
    {
        $file = Setting::get('company_logo');
        if (! $file) {
            return null;
        }

        $path = public_path('uploads/' . basename($file));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! is_file($path) || ! in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            return null;
        }

        return 'data:image/' . ($extension === 'jpg' ? 'jpeg' : $extension) . ';base64,' . base64_encode(file_get_contents($path));
    }
}
