<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceService
{
    /** Weekly off days from Settings, as Carbon day numbers (0 = Sunday ... 5 = Friday). */
    public function weeklyOffDays(): array
    {
        $raw = (string) Setting::get('weekly_off_days', '5');

        return array_map('intval', array_filter(explode(',', $raw), 'strlen'));
    }

    /**
     * Weekly offs and holidays between two dates, keyed by Y-m-d.
     * Example: ['2026-09-04' => 'Weekly off', '2026-12-16' => 'Victory Day']
     */
    public function offDaysFor(Carbon $from, Carbon $to): array
    {
        $holidays = Holiday::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()->mapWithKeys(fn ($h) => [$h->date->format('Y-m-d') => $h->name])->all();

        $weekly = $this->weeklyOffDays();
        $off = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $key = $day->format('Y-m-d');
            if (isset($holidays[$key])) {
                $off[$key] = $holidays[$key];
            } elseif (in_array($day->dayOfWeek, $weekly, true)) {
                $off[$key] = 'Weekly off';
            }
        }

        return $off;
    }

    /** Reason a single date is not a working day, or null for a normal day. */
    public function offReason(Carbon $date): ?string
    {
        return $this->offDaysFor($date, $date)[$date->format('Y-m-d')] ?? null;
    }

    /** Hours between two HH:MM times, rounded to 2 decimals. 0 if either is missing or out is not later. */
    public function workingHours(?string $checkIn, ?string $checkOut): float
    {
        if (! $checkIn || ! $checkOut) {
            return 0.0;
        }

        $seconds = strtotime($checkOut) - strtotime($checkIn);

        return $seconds > 0 ? round($seconds / 3600, 2) : 0.0;
    }

    /**
     * A "present" employee who arrives after office start + grace period is stored as "late".
     * Both values come from Settings (office_start_time, late_grace_minutes).
     */
    public function resolveStatus(string $status, ?string $checkIn): string
    {
        if ($status !== 'present' || ! $checkIn) {
            return $status;
        }

        $start = (string) Setting::get('office_start_time', '09:00');
        $grace = (int) Setting::get('late_grace_minutes', 10);

        $lateAfter = Carbon::createFromTimeString($start)->addMinutes($grace);

        return Carbon::createFromTimeString($checkIn)->gt($lateAfter) ? 'late' : 'present';
    }
}
