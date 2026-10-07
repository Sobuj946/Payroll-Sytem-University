<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedEmployees();
        $this->seedUsers();
        $this->seedLeave();
        $this->seedAttendance();
    }

    private function seedEmployees(): void
    {
        // code, first, last, gender, dob, department, designation, type, joined, basic, bank, branch, address, extra components
        $rows = [
            ['Rafiqul', 'Islam', 'male', '1984-03-12', 'Administration', 'Manager', 'permanent', '2019-01-06', 90000, 'BRAC Bank', 'Gulshan', 'Flat 5B, Road 4, Dhanmondi, Dhaka', ['FOOD']],
            ['Nusrat', 'Jahan', 'female', '1990-07-22', 'Human Resources', 'HR Manager', 'permanent', '2020-02-01', 75000, 'Dutch-Bangla Bank', 'Banani', '12/A Mirpur DOHS, Dhaka', ['FOOD']],
            ['Tanvir', 'Ahmed', 'male', '1995-11-05', 'Information Technology', 'Software Engineer', 'permanent', '2022-08-15', 55000, 'Islami Bank Bangladesh', 'Uttara', 'Sector 7, Uttara, Dhaka', ['FOOD']],
            ['Farhana', 'Akter', 'female', '1992-01-30', 'Finance', 'Accountant', 'permanent', '2021-03-10', 48000, 'Sonali Bank', 'Motijheel', 'Malibagh Chowdhurypara, Dhaka', []],
            ['Mehedi', 'Hasan', 'male', '1991-09-18', 'Information Technology', 'Senior Software Engineer', 'permanent', '2020-10-01', 85000, 'City Bank', 'Gulshan', 'Block C, Bashundhara R/A, Dhaka', ['FOOD']],
            ['Sabrina', 'Sultana', 'female', '1996-04-09', 'Marketing', 'Marketing Executive', 'permanent', '2023-01-02', 38000, 'BRAC Bank', 'Dhanmondi', 'Rayer Bazar, Dhaka', []],
            ['Imran', 'Hossain', 'male', '1998-12-25', 'Information Technology', 'Software Engineer', 'probation', '2026-07-01', 45000, 'Dutch-Bangla Bank', 'Mirpur', 'Pallabi, Mirpur-12, Dhaka', []],
            ['Mahmuda', 'Khatun', 'female', '1988-06-14', 'Administration', 'Office Assistant', 'permanent', '2018-05-20', 26000, 'Sonali Bank', 'Motijheel', 'Khilgaon, Dhaka', []],
            ['Shakib', 'Chowdhury', 'male', '1993-02-02', 'Finance', 'Finance Manager', 'permanent', '2019-09-01', 80000, 'City Bank', 'Banani', 'Road 27, Banani, Dhaka', ['FOOD']],
            ['Tasnim', 'Rahman', 'female', '1997-08-27', 'Human Resources', 'HR Executive', 'permanent', '2022-04-11', 36000, 'Islami Bank Bangladesh', 'Uttara', 'Sector 10, Uttara, Dhaka', []],
            ['Arif', 'Uddin', 'male', '1994-10-03', 'Information Technology', 'Software Engineer', 'contract', '2025-02-01', 52000, 'BRAC Bank', 'Gulshan', 'Badda, Dhaka', []],
            ['Jannatul', 'Ferdous', 'female', '1999-05-16', 'Marketing', 'Marketing Executive', 'permanent', '2024-06-03', 34000, 'Dutch-Bangla Bank', 'Dhanmondi', 'Mohammadpur, Dhaka', []],
        ];

        $nidSeed = 1984123456000;
        $components = SalaryComponent::whereIn('code', ['HRA', 'MED', 'TRN', 'MOB', 'FOOD', 'PF'])->get()->keyBy('code');

        foreach ($rows as $i => [$first, $last, $gender, $dob, $dept, $desig, $type, $joined, $basic, $bank, $branch, $address, $extra]) {
            $employee = Employee::updateOrCreate(
                ['employee_code' => sprintf('EMP-%04d', $i + 1)],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => strtolower($first . '.' . $last) . '@padmatech.test',
                    'phone' => '01' . [7, 8, 9, 5, 6][$i % 5] . sprintf('%08d', 10450000 + $i * 7919),
                    'address' => $address,
                    'gender' => $gender,
                    'date_of_birth' => $dob,
                    'nid' => (string) ($nidSeed + $i * 137),
                    'emergency_contact_name' => 'Family member of ' . $first,
                    'emergency_contact_phone' => '018' . sprintf('%08d', 22001000 + $i * 311),
                    'department_id' => Department::where('name', $dept)->value('id'),
                    'designation_id' => Designation::where('name', $desig)->value('id'),
                    'employment_type' => $type,
                    'joining_date' => $joined,
                    'basic_salary' => $basic,
                    'bank_name' => $bank,
                    'bank_account_number' => sprintf('%013d', 1020300400500 + $i * 1013),
                    'bank_branch' => $branch,
                    'status' => 'active',
                ]
            );

            foreach (array_merge(['HRA', 'MED', 'TRN', 'MOB', 'PF'], $extra) as $code) {
                EmployeeSalaryComponent::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'salary_component_id' => $components[$code]->id,
                        'effective_from' => $joined,
                    ],
                    ['value' => null]
                );
            }
        }
    }

    private function seedUsers(): void
    {
        $password = Hash::make('Password@123'); // demo password, documented in the README

        $users = [
            ['System Administrator', 'admin@padmatech.test', 'admin', null],
            ['Nusrat Jahan', 'nusrat.jahan@padmatech.test', 'hr_manager', 'EMP-0002'],
            ['Farhana Akter', 'farhana.akter@padmatech.test', 'payroll_officer', 'EMP-0004'],
            ['Tanvir Ahmed', 'tanvir.ahmed@padmatech.test', 'employee', 'EMP-0003'],
        ];

        foreach ($users as [$name, $email, $role, $code]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'role_id' => Role::where('name', $role)->value('id'),
                'employee_id' => $code ? Employee::where('employee_code', $code)->value('id') : null,
                'status' => 'active',
            ]);
        }
    }

    private function seedLeave(): void
    {
        $year = now()->year;
        $paidTypes = LeaveType::where('is_paid', true)->get();

        foreach (Employee::all() as $employee) {
            foreach ($paidTypes as $type) {
                LeaveBalance::updateOrCreate(
                    ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                    ['allocated' => $type->days_per_year, 'used' => 0]
                );
            }
        }

        $casual = LeaveType::where('name', 'Casual Leave')->first();
        $sick = LeaveType::where('name', 'Sick Leave')->first();
        $unpaid = LeaveType::where('name', 'Unpaid Leave')->first();
        $hr = User::where('email', 'nusrat.jahan@padmatech.test')->first();

        $sample = [
            ['EMP-0003', $casual, '2026-10-20', '2026-10-21', 2, 'Family function in hometown', 'pending'],
            ['EMP-0006', $sick, '2026-09-08', '2026-09-09', 2, 'Fever and doctor advised rest', 'approved'],
            ['EMP-0012', $unpaid, '2026-09-14', '2026-09-16', 3, 'Personal matter outside Dhaka', 'approved'],
            ['EMP-0007', $casual, '2026-09-22', '2026-09-22', 1, 'Bank work', 'rejected'],
        ];

        foreach ($sample as [$code, $type, $start, $end, $days, $reason, $status]) {
            $employee = Employee::where('employee_code', $code)->first();
            LeaveRequest::updateOrCreate(
                ['employee_id' => $employee->id, 'start_date' => $start, 'end_date' => $end],
                [
                    'leave_type_id' => $type->id,
                    'days' => $days,
                    'reason' => $reason,
                    'status' => $status,
                    'approved_by' => $status === 'pending' ? null : $hr?->id,
                    'approved_at' => $status === 'pending' ? null : now(),
                    'remarks' => $status === 'rejected' ? 'Project deadline that week' : null,
                ]
            );

            if ($status === 'approved' && $type->is_paid) {
                LeaveBalance::where(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026])
                    ->increment('used', $days);
            }
        }
    }

    private function seedAttendance(): void
    {
        mt_srand(2026); // same demo data on every fresh seed

        $first = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $last = $first->copy()->endOfMonth();
        $holidays = Holiday::pluck('date')->map(fn ($d) => $d->toDateString())->all();
        $weeklyOff = array_map('intval', explode(',', \App\Models\Setting::get('weekly_off_days', '5')));

        $approvedLeave = LeaveRequest::where('status', 'approved')->get();

        foreach (Employee::all() as $employee) {
            $rows = [];
            for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
                if (in_array($day->dayOfWeek, $weeklyOff, true)
                    || in_array($day->toDateString(), $holidays, true)
                    || $day->lt($employee->joining_date)) {
                    continue;
                }

                $onLeave = $approvedLeave->contains(
                    fn ($l) => $l->employee_id === $employee->id && $day->betweenIncluded($l->start_date, $l->end_date)
                );

                $in = $out = null;
                $hours = 0;
                $remarks = null;

                if ($onLeave) {
                    $status = 'leave';
                    $remarks = 'Approved leave';
                } else {
                    $roll = mt_rand(1, 100);
                    if ($roll <= 3) {
                        $status = 'absent';
                    } elseif ($roll <= 6) {
                        $status = 'half_day';
                        $in = '09:0' . mt_rand(0, 9) . ':00';
                        $out = '13:' . sprintf('%02d', mt_rand(0, 30)) . ':00';
                        $hours = 4.0;
                    } else {
                        $late = $roll <= 14;
                        $status = $late ? 'late' : 'present';
                        $in = $late ? '09:' . sprintf('%02d', mt_rand(16, 55)) . ':00' : '08:' . sprintf('%02d', mt_rand(40, 59)) . ':00';
                        $out = '17:' . sprintf('%02d', mt_rand(30, 59)) . ':00';
                        $hours = round(Carbon::parse($in)->diffInMinutes(Carbon::parse($out)) / 60, 2);
                    }
                }

                $rows[] = [
                    'employee_id' => $employee->id,
                    'date' => $day->toDateString(),
                    'check_in' => $in,
                    'check_out' => $out,
                    'working_hours' => $hours,
                    'status' => $status,
                    'remarks' => $remarks,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            Attendance::upsert($rows, ['employee_id', 'date'], ['check_in', 'check_out', 'working_hours', 'status', 'remarks', 'updated_at']);
        }
    }
}
