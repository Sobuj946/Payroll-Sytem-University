<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'company_name' => 'Padma Tech Solutions Ltd.',
            'company_address' => 'House 24, Road 11, Banani, Dhaka-1213',
            'company_phone' => '+880 2-5501 2345',
            'company_email' => 'hr@padmatech.test',
            'currency_symbol' => '৳',
            // Carbon dayOfWeek numbers, comma separated (0 = Sunday ... 5 = Friday, 6 = Saturday)
            'weekly_off_days' => '5',
            // calendar_working_days = month's real working days; fixed = use fixed_working_days
            'working_day_basis' => 'calendar_working_days',
            'fixed_working_days' => '30',
            'office_start_time' => '09:00',
            'late_grace_minutes' => '10',
            'half_day_min_hours' => '4',
            'tax_slabs_note' => 'Sample slabs for demonstration only. Replace with the rules your organization follows.',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
