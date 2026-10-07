<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['Casual Leave', 10, true],
            ['Sick Leave', 14, true],
            ['Annual Leave', 15, true],
            ['Emergency Leave', 5, true],
            ['Unpaid Leave', 0, false],
        ];

        foreach ($types as [$name, $days, $paid]) {
            LeaveType::updateOrCreate(
                ['name' => $name],
                ['days_per_year' => $days, 'is_paid' => $paid, 'status' => 'active']
            );
        }
    }
}
