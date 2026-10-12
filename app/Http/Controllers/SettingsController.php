<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\TaxSlab;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Company details and the business rules payroll reads from the database. */
class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $days = array_filter(explode(',', (string) Setting::get('weekly_off_days', '5')), 'strlen');

        return view('settings.index', [
            'tab' => in_array($request->query('tab'), ['company', 'attendance', 'payroll', 'holidays', 'tax'], true) ? $request->query('tab') : 'company',
            's' => [
                'company_name' => Setting::get('company_name', ''),
                'company_address' => Setting::get('company_address', ''),
                'company_phone' => Setting::get('company_phone', ''),
                'company_email' => Setting::get('company_email', ''),
                'company_logo' => Setting::get('company_logo'),
                'weekly_off_days' => array_map('intval', $days),
                'office_start_time' => Setting::get('office_start_time', '09:00'),
                'late_grace_minutes' => Setting::get('late_grace_minutes', 10),
                'working_day_basis' => Setting::get('working_day_basis', 'calendar_working_days'),
                'fixed_working_days' => Setting::get('fixed_working_days', 30),
                'deduction_base' => Setting::get('deduction_base', 'gross'),
                'late_days_per_deduction' => Setting::get('late_days_per_deduction', 0),
                'tax_slabs_note' => Setting::get('tax_slabs_note', ''),
            ],
            'holidays' => Holiday::orderByDesc('date')->limit(80)->get(),
            'slabSets' => TaxSlab::orderByDesc('effective_from')->orderBy('from_amount')->get()->groupBy(fn ($slab) => $slab->effective_from->format('Y-m-d')),
        ]);
    }

    public function updateCompany(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ], [
            'company_name.required' => 'Please enter the organization name.',
            'company_email.email' => 'Please enter a valid email address.',
            'logo.image' => 'The logo must be an image file.',
            'logo.mimes' => 'The logo must be a PNG or JPG file.',
            'logo.max' => 'The logo may not be larger than 1 MB.',
        ]);

        if ($request->hasFile('logo')) {
            $this->deleteLogo();
            $name = 'logo-' . Str::uuid() . '.' . $request->file('logo')->extension();
            $request->file('logo')->move(public_path('uploads'), $name);
            Setting::put('company_logo', $name);
        }

        $this->save(Arr::only($data, ['company_name', 'company_address', 'company_phone', 'company_email']), 'company');

        return $this->back('company', 'The company details have been saved.');
    }

    public function removeLogo()
    {
        $this->deleteLogo();
        Setting::put('company_logo', '');
        AuditService::log('updated', 'settings', 'The company logo was removed');

        return $this->back('company', 'The logo has been removed.');
    }

    public function updateAttendance(Request $request)
    {
        $data = $request->validate([
            'weekly_off_days' => ['nullable', 'array'],
            'weekly_off_days.*' => ['integer', 'between:0,6'],
            'office_start_time' => ['required', 'date_format:H:i'],
            'late_grace_minutes' => ['required', 'integer', 'between:0,120'],
        ], [
            'office_start_time.required' => 'Please enter the office start time.',
            'office_start_time.date_format' => 'Enter the time as HH:MM.',
            'late_grace_minutes.required' => 'Please enter the grace period (0 for none).',
            'late_grace_minutes.between' => 'The grace period must be between 0 and 120 minutes.',
        ]);

        $days = collect($data['weekly_off_days'] ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->values();

        $this->save([
            'weekly_off_days' => $days->implode(','),
            'office_start_time' => $data['office_start_time'],
            'late_grace_minutes' => $data['late_grace_minutes'],
        ], 'attendance');

        return $this->back('attendance', 'The attendance rules have been saved.');
    }

    public function updatePayroll(Request $request)
    {
        $data = $request->validate([
            'working_day_basis' => ['required', Rule::in(['calendar_working_days', 'fixed'])],
            'fixed_working_days' => ['required', 'integer', 'between:20,31'],
            'deduction_base' => ['required', Rule::in(['gross', 'basic'])],
            'late_days_per_deduction' => ['required', 'integer', 'between:0,30'],
        ], [
            'fixed_working_days.between' => 'Fixed working days must be between 20 and 31.',
            'late_days_per_deduction.between' => 'This must be between 0 (switched off) and 30.',
        ]);

        $this->save($data, 'payroll');

        return $this->back('payroll', 'The payroll rules have been saved. They apply the next time payroll is processed.');
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('holidays', 'date')],
            'name' => ['required', 'string', 'max:100'],
        ], [
            'date.required' => 'Please choose the date.',
            'date.unique' => 'This date is already marked as a holiday.',
            'name.required' => 'Please enter the holiday name.',
        ]);

        $holiday = Holiday::create($data);
        AuditService::log('created', 'settings', "Holiday {$holiday->name} on {$data['date']} was added", $holiday->id);

        return $this->back('holidays', "{$holiday->name} has been added.");
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->delete();
        AuditService::log('deleted', 'settings', "Holiday {$holiday->name} on {$holiday->date->format('Y-m-d')} was removed", $holiday->id);

        return $this->back('holidays', "{$holiday->name} has been removed.");
    }

    public function storeSlab(Request $request)
    {
        $data = $request->validate([
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'from_amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'to_amount' => ['nullable', 'numeric', 'gt:from_amount', 'max:999999999'],
            'rate_percent' => ['required', 'numeric', 'between:0,100'],
        ], [
            'effective_from.required' => 'Please choose the date these rates start.',
            'from_amount.required' => 'Please enter where the slab starts.',
            'to_amount.gt' => 'The slab must end above where it starts. Leave it empty for "and above".',
            'rate_percent.required' => 'Please enter the tax rate.',
            'rate_percent.between' => 'The rate must be between 0 and 100.',
        ]);

        // Slabs of the same set (same start date) must not overlap.
        $newTo = $data['to_amount'] ?? INF;
        $overlap = TaxSlab::whereDate('effective_from', $data['effective_from'])->get()->contains(function ($slab) use ($data, $newTo) {
            $existingTo = $slab->to_amount === null ? INF : (float) $slab->to_amount;

            return (float) $data['from_amount'] < $existingTo && $newTo > (float) $slab->from_amount;
        });

        if ($overlap) {
            throw new BusinessRuleException('This slab overlaps another slab that starts on the same date.');
        }

        $slab = TaxSlab::create($data);
        AuditService::log('created', 'settings', "Tax slab {$data['from_amount']} - " . ($data['to_amount'] ?? 'and above') . " at {$data['rate_percent']}% (from {$data['effective_from']}) was added", $slab->id);

        return $this->back('tax', 'The tax slab has been added.');
    }

    public function destroySlab(TaxSlab $taxSlab)
    {
        $taxSlab->delete();
        AuditService::log('deleted', 'settings', "Tax slab {$taxSlab->from_amount} - " . ($taxSlab->to_amount ?? 'and above') . " (from {$taxSlab->effective_from->format('Y-m-d')}) was removed", $taxSlab->id);

        return $this->back('tax', 'The tax slab has been removed.');
    }

    // ------------------------------------------------------------------

    /** Saves the values and records which ones really changed. */
    private function save(array $values, string $area): void
    {
        $changed = [];

        foreach ($values as $key => $value) {
            $value = (string) ($value ?? '');

            if ((string) Setting::get($key, '') !== $value) {
                Setting::put($key, $value);
                $changed[] = $key;
            }
        }

        if ($changed) {
            AuditService::log('updated', 'settings', "Settings changed ({$area}): " . implode(', ', $changed));
        }
    }

    private function deleteLogo(): void
    {
        $old = Setting::get('company_logo');

        if ($old) {
            File::delete(public_path('uploads/' . basename($old)));
        }
    }

    private function back(string $tab, string $message)
    {
        return redirect()->route('settings.index', ['tab' => $tab])->with('success', $message);
    }
}
