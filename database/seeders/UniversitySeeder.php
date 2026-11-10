<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Faculty;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\PayGrade;
use App\Models\SalaryComponent;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Starting data for a university: name, faculties and departments, posts, pay grades, leave types,
 * allowances and deductions, holidays. Safe to run more than once.
 *
 * IMPORTANT: every figure and structure below is a SAMPLE to make the system usable on the first day.
 * Check it against the official pay scale, the university's service rules and its organogram, and correct it
 * in the system (Settings, Faculties, Departments, Designations, Salary > Components, Pay grades, Leave types).
 */
class UniversitySeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->faculties();
        $this->designations();
        $this->payGrades();
        $this->leaveTypes();
        $this->components();
        $this->holidays();
    }

    private function settings(): void
    {
        $values = [
            'company_name' => 'Hajee Mohammad Danesh Science and Technology University',
            'company_short_name' => 'HSTU',
            'system_title' => 'HSTU Payroll Management System',
            'company_address' => 'Basherhat, Dinajpur-5200, Bangladesh',
            'employee_code_prefix' => 'HSTU',
            // Government offices in Bangladesh are closed on Friday and Saturday.
            'weekly_off_days' => '5,6',
            'office_start_time' => '09:00',
            'late_grace_minutes' => '10',
            // Monthly salary: one day of unpaid leave is one thirtieth. Change in Settings > Payroll rules.
            'working_day_basis' => 'fixed',
            'fixed_working_days' => '30',
        ];

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function faculties(): void
    {
        $structure = [
            'Faculty of Agriculture' => ['Agriculture', ['Agronomy', 'Soil Science', 'Horticulture', 'Plant Pathology', 'Entomology']],
            'Faculty of Veterinary and Animal Science' => ['Veterinary', ['Anatomy and Histology', 'Physiology', 'Animal Science']],
            'Faculty of Engineering' => ['Engineering', ['Civil Engineering', 'Electrical and Electronic Engineering', 'Mechanical Engineering']],
            'Faculty of Computer Science and Engineering' => ['CSE', ['Computer Science and Engineering']],
            'Faculty of Business Studies' => ['Business', ['Management', 'Accounting and Information Systems', 'Marketing']],
            'Faculty of Science' => ['Science', ['Mathematics', 'Physics', 'Chemistry', 'Statistics']],
            'Faculty of Social Science and Humanities' => ['Humanities', ['English', 'Economics', 'Sociology']],
        ];

        foreach ($structure as $name => [$short, $departments]) {
            $faculty = Faculty::updateOrCreate(['name' => $name], ['short_name' => $short, 'status' => 'active']);

            foreach ($departments as $departmentName) {
                Department::updateOrCreate(
                    ['name' => $departmentName],
                    ['faculty_id' => $faculty->id, 'type' => 'academic', 'status' => 'active']
                );
            }
        }

        foreach ([
            'Office of the Vice-Chancellor', "Registrar's Office", 'Finance and Accounts Office', 'Office of the Controller of Examinations',
            'Library', 'Medical Centre', 'Engineering and Works Section', 'Transport Section',
        ] as $office) {
            Department::updateOrCreate(['name' => $office], ['faculty_id' => null, 'type' => 'administrative', 'status' => 'active']);
        }
    }

    private function designations(): void
    {
        $posts = [
            'teacher' => ['Professor', 'Associate Professor', 'Assistant Professor', 'Lecturer'],
            'officer' => ['Registrar', 'Controller of Examinations', 'Librarian', 'Deputy Registrar', 'Assistant Registrar', 'Section Officer', 'Medical Officer', 'Accountant'],
            'staff' => ['Office Assistant cum Computer Typist', 'Lab Assistant', 'Library Assistant', 'Driver', 'Electrician', 'Cashier', 'Office Support Staff'],
        ];

        foreach ($posts as $category => $names) {
            foreach ($names as $name) {
                Designation::updateOrCreate(['name' => $name], ['category' => $category, 'status' => 'active']);
            }
        }
    }

    private function payGrades(): void
    {
        // grade => [starting basic, highest basic]. SAMPLE of the national pay scale, to be checked against the official scale.
        $scale = [
            1 => [78000, null], 2 => [66000, 76490], 3 => [56500, 74400], 4 => [50000, 71200], 5 => [43000, 69850],
            6 => [35500, 67010], 7 => [29000, 63410], 8 => [23000, 55480], 9 => [22000, 53060], 10 => [16000, 38640],
            11 => [12500, 30230], 12 => [11300, 27300], 13 => [11000, 26590], 14 => [10200, 24680], 15 => [9700, 23490],
            16 => [9300, 22490], 17 => [9000, 21800], 18 => [8800, 21310], 19 => [8500, 20570], 20 => [8250, 20010],
        ];

        foreach ($scale as $number => [$min, $max]) {
            PayGrade::updateOrCreate(['grade_no' => $number], [
                'name' => "Grade {$number}", 'min_basic' => $min, 'max_basic' => $max, 'increment_percent' => 5, 'status' => 'active',
            ]);
        }
    }

    private function leaveTypes(): void
    {
        // name => [days a year (0 = no limit), paid]. SAMPLE, to be checked against the university's leave rules.
        $types = [
            'Casual Leave' => [20, true], 'Earned Leave' => [30, true], 'Medical Leave' => [14, true], 'Maternity Leave' => [180, true],
            'Study Leave' => [0, true], 'Duty / Training Leave' => [0, true], 'Leave Without Pay' => [0, false],
        ];

        foreach ($types as $name => [$days, $paid]) {
            LeaveType::updateOrCreate(['name' => $name], ['days_per_year' => $days, 'is_paid' => $paid, 'status' => 'active']);
        }
    }

    private function components(): void
    {
        // code => [name, type, calculated as, default, how it is used, taxable]
        // The defaults marked SAMPLE are examples. The real rates and amounts are set in Salary > Components.
        $list = [
            ['HRA', 'House Rent Allowance', 'earning', 'percent', 50, 'structure', true],      // SAMPLE rate
            ['MED', 'Medical Allowance', 'earning', 'fixed', 1500, 'structure', true],         // SAMPLE amount
            ['EDU', 'Education Allowance', 'earning', 'fixed', 0, 'structure', true],
            ['RESEARCH', 'Research Allowance', 'earning', 'fixed', 0, 'structure', true],
            ['CHARGE', 'Charge / Additional Duty Allowance', 'earning', 'fixed', 0, 'adjustment', true],
            ['EXAMHON', 'Examination Honorarium', 'earning', 'fixed', 0, 'adjustment', true],
            ['HON', 'Honorarium (visiting teacher)', 'earning', 'fixed', 0, 'adjustment', true],
            ['BOISHAKHI', 'Bangla New Year Allowance', 'earning', 'fixed', 0, 'adjustment', true],
            ['PF', 'Contributory Provident Fund', 'deduction', 'percent', 10, 'structure', false], // SAMPLE rate
            ['WELFARE', 'Welfare Fund', 'deduction', 'fixed', 0, 'structure', false],
            ['GINS', 'Group Insurance', 'deduction', 'fixed', 0, 'structure', false],
            ['HBLOAN', 'House Building Loan Instalment', 'deduction', 'fixed', 0, 'adjustment', false],
            ['VEHLOAN', 'Vehicle Loan Instalment', 'deduction', 'fixed', 0, 'adjustment', false],
        ];

        foreach ($list as [$code, $name, $type, $calc, $default, $source, $taxable]) {
            SalaryComponent::updateOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'calc_type' => $calc, 'default_value' => $default,
                'source' => $source, 'is_taxable' => $taxable, 'status' => 'active',
            ]);
        }
    }

    private function holidays(): void
    {
        // Fixed-date national days only. Eid and other moon-calendar holidays are added by the administrator each year (Settings > Holidays).
        $fixed = ['02-21' => "International Mother Language Day", '03-26' => 'Independence Day', '04-14' => 'Pohela Boishakh', '05-01' => 'May Day', '12-16' => 'Victory Day', '12-25' => 'Christmas Day'];

        foreach ([2026, 2027] as $year) {
            foreach ($fixed as $day => $name) {
                Holiday::updateOrCreate(['date' => "{$year}-{$day}"], ['name' => $name]);
            }
        }
    }
}
