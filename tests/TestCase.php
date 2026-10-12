<?php

namespace Tests;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\PayrollService;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SalaryComponentSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    private static int $sequence = 0;

    /**
     * A page that answers 500 must never pass a test silently (for example one that only checks
     * "no validation errors"). Set this to false in a test that wants to look at the 500 itself.
     */
    protected bool $failOnServerError = true;

    /**
     * Safety stop: the database is wiped before every test run, so refuse to touch
     * anything that is not clearly a test database.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $name = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($name, '_test')) {
            throw new RuntimeException("Refusing to run: the database \"{$name}\" is not a test database. Its name must end with _test.");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Roles, permissions, settings, departments, leave types and salary components, as in a fresh install.
        foreach ([RolePermissionSeeder::class, SettingSeeder::class, OrganizationSeeder::class, LeaveTypeSeeder::class, SalaryComponentSeeder::class] as $seeder) {
            $this->seed($seeder);
        }

        $this->app['events']->listen(RequestHandled::class, function (RequestHandled $event) {
            if (! $this->failOnServerError || $event->response->getStatusCode() < 500) {
                return;
            }

            $exception = $event->response->exception ?? null;

            throw new RuntimeException(sprintf(
                'Server error %d on %s %s%s',
                $event->response->getStatusCode(),
                $event->request->method(),
                $event->request->path(),
                $exception ? ': ' . get_class($exception) . ': ' . $exception->getMessage() . ' (' . basename($exception->getFile()) . ':' . $exception->getLine() . ')' : ''
            ));
        });
    }

    // ------------------------------------------------------------------
    //  Builders
    // ------------------------------------------------------------------

    protected function makeEmployee(array $overrides = []): Employee
    {
        $n = ++self::$sequence;

        return Employee::create(array_merge([
            'employee_code' => sprintf('EMP-%04d', $n),
            'first_name' => 'Test',
            'last_name' => 'Employee' . $n,
            'email' => "employee{$n}@example.test",
            'phone' => '017' . sprintf('%08d', 10000000 + $n),
            'gender' => 'male',
            'date_of_birth' => '1992-05-10',
            'nid' => sprintf('%013d', 1000000000000 + $n),
            'department_id' => Department::orderBy('id')->value('id'),
            'designation_id' => Designation::orderBy('id')->value('id'),
            'employment_type' => 'permanent',
            'joining_date' => '2022-01-01',
            'basic_salary' => 50000,
            'status' => 'active',
        ], $overrides));
    }

    protected function userWithRole(string $role, ?Employee $employee = null): User
    {
        $n = ++self::$sequence;

        return User::create([
            'name' => ucfirst(str_replace('_', ' ', $role)) . " {$n}",
            'email' => "user{$n}@example.test",
            'password' => 'Password1',
            'role_id' => Role::where('name', $role)->value('id'),
            'employee_id' => $employee?->id,
            'status' => 'active',
        ]);
    }

    protected function addToSalary(Employee $employee, string $code, ?float $value = null, string $from = '2022-01-01'): EmployeeSalaryComponent
    {
        return EmployeeSalaryComponent::create([
            'employee_id' => $employee->id,
            'salary_component_id' => SalaryComponent::where('code', $code)->value('id'),
            'value' => $value,
            'effective_from' => $from,
        ]);
    }

    protected function makePeriod(User $by, int $year = 2026, int $month = 9, string $status = 'draft'): PayrollPeriod
    {
        $start = sprintf('%d-%02d-01', $year, $month);

        return PayrollPeriod::create([
            'year' => $year,
            'month' => $month,
            'start_date' => $start,
            'end_date' => date('Y-m-t', strtotime($start)),
            'status' => $status,
            'created_by' => $by->id,
        ]);
    }

    /**
     * The standard payroll test case: September 2026 (30 days, 4 Fridays, so 26 working days),
     * one employee on a 50,000 basic with House Rent 20% (10,000) and Medical 5,000.
     * Basic + allowances = 65,000, so one day is worth 65,000 / 26 = 2,500.
     * Tax slabs are removed so that amounts are easy to check; the tax tests add their own.
     *
     * @return array{0: User, 1: Employee, 2: PayrollPeriod}
     */
    protected function payrollScenario(): array
    {
        TaxSlab::query()->delete();

        $admin = $this->userWithRole('admin');
        $employee = $this->makeEmployee(['basic_salary' => 50000]);
        $this->addToSalary($employee, 'HRA');
        $this->addToSalary($employee, 'MED');

        return [$admin, $employee, $this->makePeriod($admin)];
    }

    protected function processPayroll(PayrollPeriod $period, User $by): array
    {
        return app(PayrollService::class)->process($period, $by);
    }

    /** Takes a period all the way to Approved. */
    protected function approvePayroll(PayrollPeriod $period, User $by): void
    {
        $service = app(PayrollService::class);
        $service->process($period, $by);
        $service->markReviewed($period, $by);
        $service->approve($period, $by);
    }

    /** A real 1x1 PNG, so the upload tests do not need the PHP GD extension. */
    protected function pngFile(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')
        );
    }

    protected function assertMoney(float|int $expected, mixed $actual, string $message = ''): void
    {
        $this->assertEqualsWithDelta((float) $expected, (float) $actual, 0.01, $message);
    }
}
