<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SeedDemoDataset extends Command
{
    public const RECORD_COUNTS = [
        'employees' => 200,
        'attendances' => 3600,
        'leave_requests' => 400,
        'employee_salary_components' => 200,
        'payrolls' => 300,
        'payroll_items' => 300,
    ];

    protected $signature = 'pms:seed-demo-dataset {profile : padmatech}';

    protected $description = 'Add a repeatable 5,000-row synthetic payroll dataset';

    public function handle(): int
    {
        $profile = $this->argument('profile');

        if ($profile !== 'padmatech') {
            $this->error('Choose the padmatech profile.');

            return self::FAILURE;
        }

        $expectedDatabase = 'payroll_pms';
        $actualDatabase = DB::connection()->getDatabaseName();

        if ($actualDatabase !== $expectedDatabase) {
            $this->error("This profile must run against {$expectedDatabase}; the current database is {$actualDatabase}.");

            return self::FAILURE;
        }

        if (! Schema::hasTable('employees') || ! Schema::hasTable('attendances')) {
            $this->error('Database migrations are missing. Run php artisan migrate first.');

            return self::FAILURE;
        }

        try {
            $this->seedDataset();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $counts = $this->datasetCounts();
        $total = array_sum($counts);

        foreach ($counts as $table => $count) {
            $this->line("{$table}: {$count}");
        }

        if ($total !== 5000) {
            $this->error("Expected 5,000 dataset rows but found {$total}.");

            return self::FAILURE;
        }

        $this->info("Verified {$total} synthetic dataset rows in {$expectedDatabase}.");

        return self::SUCCESS;
    }

    private function seedDataset(): void
    {
        $departments = Department::where('status', 'active')->orderBy('id')->pluck('id')->all();
        $designations = Designation::where('status', 'active')->orderBy('id')->pluck('id')->all();
        $component = SalaryComponent::where('code', 'HRA')->where('status', 'active')->first();
        $leaveType = LeaveType::where('status', 'active')->orderBy('id')->first();
        $admin = User::where('status', 'active')->whereHas('role', fn ($query) => $query->where('name', 'admin'))->orderBy('id')->first();
        if ($departments === [] || $designations === [] || ! $component || ! $leaveType || ! $admin) {
            throw new RuntimeException('The database needs an active administrator, departments, designations, leave types and salary components.');
        }

        $employees = [];

        DB::transaction(function () use ($departments, $designations, $component, $leaveType, $admin, &$employees) {
            for ($i = 1; $i <= self::RECORD_COUNTS['employees']; $i++) {
                $code = $this->employeeCode($i);
                $joiningDate = Carbon::create(2018 + ($i % 6), (($i - 1) % 12) + 1, (($i - 1) % 27) + 1);
                $basicSalary = 25000 + (($i - 1) % 10) * 1500;

                $employee = Employee::updateOrCreate(
                    ['employee_code' => $code],
                    [
                        'first_name' => $this->firstName($i),
                        'last_name' => $this->lastName($i),
                        'email' => $this->employeeEmail($i),
                        'phone' => '01' . [3, 5, 6, 7, 8, 9][$i % 6] . sprintf('%08d', 40000000 + $i),
                        'address' => 'Demo address, Dhaka, Bangladesh',
                        'gender' => $i % 2 === 0 ? 'female' : 'male',
                        'date_of_birth' => Carbon::create(1970 + ($i % 25), (($i - 1) % 12) + 1, (($i - 1) % 27) + 1),
                        'nid' => sprintf('%013d', 8000000000000 + $i),
                        'emergency_contact_name' => 'Demo family contact ' . $i,
                        'emergency_contact_phone' => '018' . sprintf('%08d', 50000000 + $i),
                        'department_id' => $departments[($i - 1) % count($departments)],
                        'designation_id' => $designations[($i - 1) % count($designations)],
                        'employment_type' => $i % 5 === 0 ? 'contract' : 'permanent',
                        'joining_date' => $joiningDate->toDateString(),
                        'basic_salary' => $basicSalary,
                        'bank_name' => ['Sonali Bank PLC', 'Agrani Bank PLC', 'Janata Bank PLC'][$i % 3],
                        'bank_account_number' => sprintf('%013d', 7000000000000 + $i),
                        'bank_branch' => 'Dhaka',
                        'status' => 'active',
                    ]
                );

                $employees[] = $employee;

                EmployeeSalaryComponent::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'salary_component_id' => $component->id,
                        'effective_from' => $joiningDate->toDateString(),
                    ],
                    ['value' => null]
                );
            }

            $this->seedAttendance($employees);
            $this->seedLeaveRequests($employees, $leaveType, $admin);
            $this->seedPayroll($employees, $component, $admin);
        }, 3);
    }

    /** @param list<Employee> $employees */
    private function seedAttendance(array $employees): void
    {
        $weeklyOff = array_map('intval', array_filter(explode(',', (string) Setting::get('weekly_off_days', '5')), 'strlen'));
        $rows = [];
        $base = Carbon::create(2024, 1, 1);

        foreach ($employees as $employeeIndex => $employee) {
            $date = $base->copy()->addWeeks($employeeIndex % 9);

            for ($dayIndex = 0; $dayIndex < 18;) {
                if (! in_array($date->dayOfWeek, $weeklyOff, true)) {
                    $roll = ($employeeIndex + $dayIndex) % 20;
                    $status = $roll < 2 ? 'absent' : ($roll < 5 ? 'late' : ($roll < 7 ? 'half_day' : 'present'));
                    $checkIn = in_array($status, ['absent'], true) ? null : ($status === 'late' ? '09:25:00' : '08:55:00');
                    $checkOut = match ($status) {
                        'absent' => null,
                        'half_day' => '13:00:00',
                        default => '17:00:00',
                    };
                    $hours = $checkIn && $checkOut
                        ? round((strtotime($checkOut) - strtotime($checkIn)) / 3600, 2)
                        : 0;

                    $rows[] = [
                        'employee_id' => $employee->id,
                        'date' => $date->toDateString(),
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'working_hours' => $hours,
                        'status' => $status,
                        'remarks' => 'Synthetic demo dataset',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $dayIndex++;
                }

                $date->addDay();
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Attendance::upsert($chunk, ['employee_id', 'date'], ['check_in', 'check_out', 'working_hours', 'status', 'remarks', 'updated_at']);
        }
    }

    /** @param list<Employee> $employees */
    private function seedLeaveRequests(array $employees, LeaveType $leaveType, User $admin): void
    {
        foreach ($employees as $i => $employee) {
            foreach ([0, 1] as $requestIndex) {
                $start = Carbon::create(2025, $requestIndex === 0 ? 1 : 7, 1)->addDays($i);
                $reason = sprintf('Synthetic demo dataset:padmatech:%04d:%d', $i + 1, $requestIndex + 1);
                $approved = ($i + $requestIndex) % 3 !== 0;

                LeaveRequest::updateOrCreate(
                    ['employee_id' => $employee->id, 'reason' => $reason],
                    [
                        'leave_type_id' => $leaveType->id,
                        'start_date' => $start->toDateString(),
                        'end_date' => $start->copy()->addDay()->toDateString(),
                        'days' => 2,
                        'status' => $approved ? 'approved' : 'pending',
                        'approved_by' => $approved ? $admin->id : null,
                        'approved_at' => $approved ? now() : null,
                        'remarks' => 'Synthetic demo record; not a real employee.',
                    ]
                );
            }
        }
    }

    /** @param list<Employee> $employees */
    private function seedPayroll(array $employees, SalaryComponent $component, User $admin): void
    {
        $periods = [];
        foreach ([[11, 2024], [12, 2024]] as [$month, $year]) {
            $start = Carbon::create($year, $month, 1);
            $periods[$month] = PayrollPeriod::updateOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->endOfMonth()->toDateString(),
                    'status' => 'processed',
                    'created_by' => $admin->id,
                    'approved_by' => null,
                    'approved_at' => null,
                    'notes' => 'Synthetic demo dataset; not a real payroll.',
                ]
            );
        }

        foreach ($employees as $i => $employee) {
            $months = $i < 100 ? [11, 12] : [11];

            foreach ($months as $month) {
                $allowance = round((float) $employee->basic_salary * (float) $component->default_value / 100, 2);
                $gross = (float) $employee->basic_salary + $allowance;
                $payroll = Payroll::updateOrCreate(
                    ['payroll_period_id' => $periods[$month]->id, 'employee_id' => $employee->id],
                    [
                        'basic_salary' => $employee->basic_salary,
                        'total_allowances' => $allowance,
                        'overtime' => 0,
                        'bonus' => 0,
                        'gross_salary' => $gross,
                        'tax' => 0,
                        'total_deductions' => 0,
                        'net_salary' => $gross,
                        'working_days' => 26,
                        'present_days' => 26,
                        'absent_days' => 0,
                        'unpaid_leave_days' => 0,
                        'late_count' => 0,
                    ]
                );

                PayrollItem::updateOrCreate(
                    ['payroll_id' => $payroll->id, 'name' => 'Demo housing allowance'],
                    [
                        'salary_component_id' => $component->id,
                        'type' => 'earning',
                        'amount' => $allowance,
                    ]
                );
            }
        }
    }

    /** @return array<string, int> */
    private function datasetCounts(): array
    {
        $codes = array_map(fn ($i) => $this->employeeCode($i), range(1, self::RECORD_COUNTS['employees']));
        $employeeIds = Employee::whereIn('employee_code', $codes)->pluck('id');
        $payrollIds = Payroll::whereIn('employee_id', $employeeIds)->whereHas('period', fn ($query) => $query->where('year', 2024)->whereIn('month', [11, 12]))->pluck('id');
        $componentId = SalaryComponent::where('code', 'HRA')->value('id');

        return [
            'employees' => Employee::whereIn('employee_code', $codes)->count(),
            'attendances' => Attendance::whereIn('employee_id', $employeeIds)->where('remarks', 'Synthetic demo dataset')->count(),
            'leave_requests' => LeaveRequest::whereIn('employee_id', $employeeIds)->where('reason', 'like', 'Synthetic demo dataset:padmatech:%')->count(),
            'employee_salary_components' => EmployeeSalaryComponent::whereIn('employee_id', $employeeIds)->where('salary_component_id', $componentId)->count(),
            'payrolls' => Payroll::whereIn('id', $payrollIds)->count(),
            'payroll_items' => PayrollItem::whereIn('payroll_id', $payrollIds)->where('name', 'Demo housing allowance')->count(),
        ];
    }

    private function employeeCode(int $i): string
    {
        return sprintf('PD-D%04d', $i);
    }

    private function employeeEmail(int $i): string
    {
        return sprintf('padma.demo.%04d@example.test', $i);
    }

    private function firstName(int $i): string
    {
        $names = ['Aminul', 'Farzana', 'Rafiq', 'Nusrat', 'Mahmud', 'Sharmeen', 'Tanvir', 'Sabina', 'Rezaul', 'Jannat'];

        return $names[($i - 1) % count($names)];
    }

    private function lastName(int $i): string
    {
        $names = ['Hasan', 'Akter', 'Rahman', 'Jahan', 'Islam', 'Begum', 'Ahmed', 'Khatun', 'Karim', 'Sultana'];

        return $names[(intdiv($i - 1, 10)) % count($names)] . sprintf('%03d', $i);
    }
}
