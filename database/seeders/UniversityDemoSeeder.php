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
use App\Models\PayGrade;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Demonstration people for the university version. All names, numbers and salaries are made up.
 * Run UniversitySeeder first. Every demo account uses the password Password@123.
 */
class UniversityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->employees();
        $this->users();
        $this->leave();
        $this->attendance();
    }

    private function employees(): void
    {
        // first, last, gender, born, department, post, grade, type, joined, basic, bank, branch, address
        $people = [
            ['Mahmudul', 'Hasan', 'male', '1971-03-12', 'Computer Science and Engineering', 'Professor', 3, 'permanent', '2008-07-01', 66000, 'Sonali Bank PLC', 'Basherhat', 'Teachers\' Quarter, HSTU Campus, Dinajpur'],
            ['Farzana', 'Yasmin', 'female', '1978-08-22', 'Agronomy', 'Associate Professor', 4, 'permanent', '2012-03-12', 58500, 'Agrani Bank PLC', 'Dinajpur', 'Kantaji Road, Dinajpur'],
            ['Rezaul', 'Karim', 'male', '1984-11-05', 'Electrical and Electronic Engineering', 'Assistant Professor', 6, 'permanent', '2016-09-05', 44000, 'Janata Bank PLC', 'Dinajpur', 'Munshipara, Dinajpur'],
            ['Sharmin', 'Akter', 'female', '1985-01-15', 'Management', 'Assistant Professor', 6, 'permanent', '2017-01-15', 41500, 'Sonali Bank PLC', 'Basherhat', 'Ramnagar, Dinajpur'],
            ['Tanvir', 'Ahmed', 'male', '1993-06-18', 'Computer Science and Engineering', 'Lecturer', 9, 'permanent', '2022-08-01', 28000, 'Rupali Bank PLC', 'Dinajpur', 'Shiber Bazar, Dinajpur'],
            ['Nusrat', 'Jahan', 'female', '1994-02-20', 'Mathematics', 'Lecturer', 9, 'permanent', '2023-02-20', 26500, 'Sonali Bank PLC', 'Basherhat', 'Basherhat, Dinajpur'],
            ['Abdullah', 'Al Mamun', 'male', '1980-04-10', 'Anatomy and Histology', 'Associate Professor', 4, 'permanent', '2013-06-10', 55000, 'Agrani Bank PLC', 'Dinajpur', 'Balubari, Dinajpur'],
            ['Shamima', 'Nasrin', 'female', '1996-12-06', 'English', 'Lecturer', 9, 'contract', '2024-05-06', 24000, 'Islami Bank Bangladesh PLC', 'Dinajpur', 'Bochaganj, Dinajpur'],
            ['Habibur', 'Rahman', 'male', '1975-09-30', 'Economics', 'Lecturer', null, 'visiting', '2025-02-01', 15000, 'Sonali Bank PLC', 'Dinajpur', 'Parbatipur, Dinajpur'],
            ['Abdur', 'Rahman', 'male', '1974-05-02', "Registrar's Office", 'Registrar', 2, 'permanent', '2015-01-04', 70000, 'Sonali Bank PLC', 'Basherhat', 'Officers\' Quarter, HSTU Campus'],
            ['Nasrin', 'Sultana', 'female', '1988-10-14', 'Finance and Accounts Office', 'Accountant', 10, 'permanent', '2018-03-11', 24000, 'Janata Bank PLC', 'Dinajpur', 'Uttar Balubari, Dinajpur'],
            ['Sirajul', 'Islam', 'male', '1972-07-27', 'Office of the Controller of Examinations', 'Controller of Examinations', 3, 'permanent', '2010-11-01', 60000, 'Agrani Bank PLC', 'Dinajpur', 'Officers\' Quarter, HSTU Campus'],
            ['Rokeya', 'Begum', 'female', '1981-03-03', 'Library', 'Librarian', 6, 'permanent', '2014-08-17', 40000, 'Sonali Bank PLC', 'Basherhat', 'Rajbari Road, Dinajpur'],
            ['Kamal', 'Hossain', 'male', '1986-12-09', "Registrar's Office", 'Section Officer', 10, 'permanent', '2019-04-22', 22500, 'Rupali Bank PLC', 'Dinajpur', 'Chehelgazi, Dinajpur'],
            ['Julekha', 'Khatun', 'female', '1983-06-25', "Registrar's Office", 'Assistant Registrar', 9, 'permanent', '2016-02-08', 30000, 'Janata Bank PLC', 'Dinajpur', 'Gobindanagar, Dinajpur'],
            ['Mizanur', 'Rahman', 'male', '1990-01-19', "Registrar's Office", 'Office Assistant cum Computer Typist', 16, 'permanent', '2017-10-03', 12000, 'Sonali Bank PLC', 'Basherhat', 'Basherhat, Dinajpur'],
            ['Anwar', 'Hossain', 'male', '1987-08-08', 'Computer Science and Engineering', 'Lab Assistant', 14, 'permanent', '2015-05-12', 15000, 'Agrani Bank PLC', 'Dinajpur', 'Shiber Bazar, Dinajpur'],
            ['Delwar', 'Hossain', 'male', '1982-02-11', 'Transport Section', 'Driver', 16, 'permanent', '2012-09-24', 12500, 'Sonali Bank PLC', 'Basherhat', 'Basherhat, Dinajpur'],
            ['Abul', 'Kalam', 'male', '1979-11-30', 'Engineering and Works Section', 'Electrician', 15, 'permanent', '2011-01-16', 13000, 'Janata Bank PLC', 'Dinajpur', 'Kuthibari, Dinajpur'],
            ['Rahima', 'Khatun', 'female', '1991-04-04', 'Library', 'Library Assistant', 15, 'permanent', '2018-07-29', 13500, 'Sonali Bank PLC', 'Basherhat', 'Basherhat, Dinajpur'],
            ['Sabina', 'Yeasmin', 'female', '1989-09-21', "Registrar's Office", 'Office Support Staff', 20, 'permanent', '2016-06-06', 9000, 'Rupali Bank PLC', 'Dinajpur', 'Dinajpur Sadar'],
            ['Billal', 'Hossain', 'male', '1995-03-15', 'Engineering and Works Section', 'Office Support Staff', null, 'master_roll', '2024-01-02', 8500, 'Sonali Bank PLC', 'Basherhat', 'Basherhat, Dinajpur'],
        ];

        $components = SalaryComponent::whereIn('code', ['HRA', 'MED', 'PF'])->get()->keyBy('code');

        foreach ($people as $i => [$first, $last, $gender, $born, $department, $post, $grade, $type, $joined, $basic, $bank, $branch, $address]) {
            $employee = Employee::updateOrCreate(
                ['employee_code' => sprintf('HSTU-%04d', $i + 1)],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => strtolower(str_replace(' ', '', $first . '.' . $last)) . '@hstu.example',
                    'phone' => '01' . [7, 8, 9, 5, 6][$i % 5] . sprintf('%08d', 20450000 + $i * 7919),
                    'address' => $address,
                    'gender' => $gender,
                    'date_of_birth' => $born,
                    'nid' => sprintf('%013d', 1970000000000 + $i * 7919),
                    'tin' => sprintf('%012d', 300000000000 + $i * 13579),
                    'emergency_contact_name' => 'Family member of ' . $first,
                    'emergency_contact_phone' => '018' . sprintf('%08d', 31001000 + $i * 311),
                    'department_id' => Department::where('name', $department)->value('id'),
                    'designation_id' => Designation::where('name', $post)->value('id'),
                    'pay_grade_id' => $grade ? PayGrade::where('grade_no', $grade)->value('id') : null,
                    'employment_type' => $type,
                    'joining_date' => $joined,
                    'basic_salary' => $basic,
                    'bank_name' => $bank,
                    'bank_account_number' => sprintf('%013d', 2002003004000 + $i * 1013),
                    'bank_branch' => $branch,
                    'pf_account_no' => $type === 'permanent' ? sprintf('CPF-%04d', $i + 1) : null,
                    'status' => 'active',
                ]
            );

            // People on a regular appointment get the usual allowances and the provident fund.
            $lines = $type === 'permanent' ? ['HRA', 'MED', 'PF'] : ($type === 'contract' ? ['HRA', 'MED'] : []);
            foreach ($lines as $code) {
                EmployeeSalaryComponent::updateOrCreate(
                    ['employee_id' => $employee->id, 'salary_component_id' => $components[$code]->id, 'effective_from' => $joined],
                    ['value' => null]
                );
            }
        }
    }

    private function users(): void
    {
        $password = 'Password@123'; // demo password; the user model hashes it

        $accounts = [
            ['System Administrator', 'admin@hstu.example', 'admin', null],
            ['Abdur Rahman', 'abdur.rahman@hstu.example', 'hr_manager', 'HSTU-0010'],
            ['Nasrin Sultana', 'nasrin.sultana@hstu.example', 'payroll_officer', 'HSTU-0011'],
            ['Tanvir Ahmed', 'tanvir.ahmed@hstu.example', 'employee', 'HSTU-0005'],
        ];

        foreach ($accounts as [$name, $email, $role, $code]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'role_id' => Role::where('name', $role)->value('id'),
                'employee_id' => $code ? Employee::where('employee_code', $code)->value('id') : null,
                'status' => 'active',
            ]);
        }
    }

    private function leave(): void
    {
        $casual = LeaveType::where('name', 'Casual Leave')->first();
        $teacher = Employee::where('employee_code', 'HSTU-0002')->first();
        $lecturer = Employee::where('employee_code', 'HSTU-0005')->first();
        $hr = User::where('email', 'abdur.rahman@hstu.example')->first();
        $lastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        LeaveRequest::updateOrCreate(
            ['employee_id' => $teacher->id, 'start_date' => $lastMonth->copy()->addDays(9)->toDateString(), 'end_date' => $lastMonth->copy()->addDays(10)->toDateString()],
            ['leave_type_id' => $casual->id, 'days' => 2, 'reason' => 'Family matter', 'status' => 'approved', 'approved_by' => $hr?->id, 'approved_at' => now()]
        );
        LeaveBalance::updateOrCreate(
            ['employee_id' => $teacher->id, 'leave_type_id' => $casual->id, 'year' => $lastMonth->year],
            ['allocated' => $casual->days_per_year, 'used' => 2]
        );

        LeaveRequest::updateOrCreate(
            ['employee_id' => $lecturer->id, 'start_date' => now()->addDays(14)->toDateString(), 'end_date' => now()->addDays(15)->toDateString()],
            ['leave_type_id' => $casual->id, 'days' => 2, 'reason' => 'Conference travel', 'status' => 'pending']
        );
    }

    private function attendance(): void
    {
        mt_srand(2027); // the same demo data on every fresh seed

        $first = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $last = $first->copy()->endOfMonth();
        $holidays = Holiday::pluck('date')->map(fn ($d) => $d->toDateString())->all();
        $weeklyOff = array_map('intval', array_filter(explode(',', (string) Setting::get('weekly_off_days', '5,6')), 'strlen'));
        $onLeave = LeaveRequest::where('status', 'approved')->get();

        foreach (Employee::whereIn('status', ['active'])->get() as $employee) {
            $rows = [];

            for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
                if (in_array($day->dayOfWeek, $weeklyOff, true) || in_array($day->toDateString(), $holidays, true) || $day->lt($employee->joining_date)) {
                    continue;
                }

                $leave = $onLeave->contains(fn ($l) => $l->employee_id === $employee->id && $day->betweenIncluded($l->start_date, $l->end_date));
                $in = $out = null;
                $hours = 0;

                if ($leave) {
                    $status = 'leave';
                } else {
                    $roll = mt_rand(1, 100);

                    if ($roll <= 2) {
                        $status = 'absent';
                    } else {
                        $late = $roll <= 10;
                        $status = $late ? 'late' : 'present';
                        $in = $late ? '09:' . sprintf('%02d', mt_rand(16, 50)) . ':00' : '08:' . sprintf('%02d', mt_rand(35, 59)) . ':00';
                        $out = '17:' . sprintf('%02d', mt_rand(0, 40)) . ':00';
                        $hours = round((strtotime($out) - strtotime($in)) / 3600, 2);
                    }
                }

                $rows[] = [
                    'employee_id' => $employee->id, 'date' => $day->toDateString(), 'check_in' => $in, 'check_out' => $out,
                    'working_hours' => $hours, 'status' => $status, 'remarks' => $leave ? 'Approved leave' : null,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }

            Attendance::upsert($rows, ['employee_id', 'date'], ['check_in', 'check_out', 'working_hours', 'status', 'remarks', 'updated_at']);
        }
    }
}
