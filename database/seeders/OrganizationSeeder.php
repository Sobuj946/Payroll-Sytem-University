<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Holiday;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'Information Technology' => 'Software development, infrastructure and technical support',
            'Human Resources' => 'Recruitment, employee relations and attendance',
            'Finance' => 'Accounts, payroll and budgeting',
            'Marketing' => 'Brand, campaigns and customer outreach',
            'Administration' => 'Office management and general services',
        ];
        foreach ($departments as $name => $description) {
            Department::updateOrCreate(['name' => $name], ['description' => $description, 'status' => 'active']);
        }

        $designations = [
            'Software Engineer', 'Senior Software Engineer', 'HR Executive', 'HR Manager', 'Accountant',
            'Finance Manager', 'Marketing Executive', 'Office Assistant', 'Manager',
        ];
        foreach ($designations as $name) {
            Designation::updateOrCreate(['name' => $name], ['status' => 'active']);
        }

        // Fixed-date national days only. Religious holidays depend on the moon calendar,
        // so the Admin adds them each year from the Settings screen.
        $holidays = [
            '2026-02-21' => 'International Mother Language Day',
            '2026-03-26' => 'Independence Day',
            '2026-04-14' => 'Pohela Boishakh',
            '2026-05-01' => 'May Day',
            '2026-12-16' => 'Victory Day',
            '2026-12-25' => 'Christmas Day',
        ];
        foreach ($holidays as $date => $name) {
            Holiday::updateOrCreate(['date' => $date], ['name' => $name]);
        }
    }
}
