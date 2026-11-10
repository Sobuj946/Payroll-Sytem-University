<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Faculty;
use App\Models\LeaveType;
use App\Models\PayGrade;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\Setting;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\ReportService;
use Carbon\Carbon;
use Database\Seeders\UniversityDemoSeeder;
use Database\Seeders\UniversitySeeder;
use Tests\TestCase;

/** The university version: faculties, pay grades, increments, bulk items, branding and the HSTU starting data. */
class UniversityTest extends TestCase
{
    private function grade(int $no = 9, float $min = 22000, ?float $max = 53060, float $percent = 5): PayGrade
    {
        return PayGrade::create(['grade_no' => $no, 'name' => "Grade {$no}", 'min_basic' => $min, 'max_basic' => $max, 'increment_percent' => $percent, 'status' => 'active']);
    }

    private function designation(string $name, string $category): Designation
    {
        return Designation::create(['name' => $name, 'category' => $category, 'status' => 'active']);
    }

    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Rahim', 'last_name' => 'Uddin', 'email' => 'rahim.uddin@example.test', 'phone' => '01712345678',
            'gender' => 'male', 'date_of_birth' => '1990-04-12', 'nid' => '1990123456789',
            'department_id' => Department::orderBy('id')->value('id'), 'designation_id' => Designation::orderBy('id')->value('id'),
            'employment_type' => 'permanent', 'joining_date' => '2024-01-10', 'basic_salary' => 30000,
        ], $overrides);
    }

    private function editPayload(Employee $employee, array $overrides = []): array
    {
        return array_merge([
            'first_name' => $employee->first_name, 'last_name' => $employee->last_name, 'email' => $employee->email, 'phone' => $employee->phone,
            'gender' => $employee->gender, 'date_of_birth' => $employee->date_of_birth->format('Y-m-d'), 'nid' => $employee->nid,
            'department_id' => $employee->department_id, 'designation_id' => $employee->designation_id,
            'employment_type' => $employee->employment_type, 'joining_date' => $employee->joining_date->format('Y-m-d'), 'status' => $employee->status,
        ], $overrides);
    }

    // ---------------------------------------------------------------- faculties, departments, designations

    /** TC-UNI-001: Add a faculty; the name must be unique */
    public function test_add_faculty(): void
    {
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->post(route('faculties.store'), ['name' => 'Faculty of Fisheries', 'short_name' => 'Fisheries', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('faculties', ['name' => 'Faculty of Fisheries']);

        $this->actingAs($hr)->from('/faculties/create')->post(route('faculties.store'), ['name' => 'Faculty of Fisheries', 'status' => 'active'])->assertSessionHasErrors('name');
        $this->assertSame(1, Faculty::count());
    }

    /** TC-UNI-002: A faculty with active departments cannot be deactivated; an empty one can */
    public function test_faculty_deactivation_rule(): void
    {
        $faculty = Faculty::create(['name' => 'Faculty of Science', 'status' => 'active']);
        $department = Department::create(['name' => 'Physics', 'faculty_id' => $faculty->id, 'type' => 'academic', 'status' => 'active']);
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->patch(route('faculties.status', $faculty))->assertSessionHas('error');
        $this->assertSame('active', $faculty->fresh()->status);

        $department->update(['status' => 'inactive']);
        $this->actingAs($hr)->patch(route('faculties.status', $faculty))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $faculty->fresh()->status);
    }

    /** TC-UNI-003: HR and the payroll officer see faculties, an employee does not, and only HR changes them */
    public function test_faculty_permissions(): void
    {
        $this->actingAs($this->userWithRole('hr_manager'))->get('/faculties')->assertOk();
        $this->actingAs($this->userWithRole('payroll_officer'))->get('/faculties')->assertOk();
        $this->actingAs($this->userWithRole('employee', $this->makeEmployee()))->get('/faculties')->assertForbidden();

        $this->actingAs($this->userWithRole('payroll_officer'))->post(route('faculties.store'), ['name' => 'X Faculty', 'status' => 'active'])->assertForbidden();
    }

    /** TC-UNI-004: A department belongs to a faculty and is academic or administrative */
    public function test_department_with_faculty_and_type(): void
    {
        $faculty = Faculty::create(['name' => 'Faculty of Agriculture', 'status' => 'active']);
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->post(route('departments.store'), ['name' => 'Agronomy', 'faculty_id' => $faculty->id, 'type' => 'academic', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame($faculty->id, Department::where('name', 'Agronomy')->value('faculty_id'));

        $this->actingAs($hr)->post(route('departments.store'), ['name' => "Registrar's Office", 'type' => 'administrative', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertNull(Department::where('name', "Registrar's Office")->value('faculty_id'));

        $this->actingAs($hr)->from('/departments/create')->post(route('departments.store'), ['name' => 'Club', 'type' => 'club', 'status' => 'active'])->assertSessionHasErrors('type');
    }

    /** TC-UNI-005: A designation is for a teacher, an officer or staff */
    public function test_designation_category(): void
    {
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->post(route('designations.store'), ['name' => 'Professor', 'category' => 'teacher', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('teacher', Designation::where('name', 'Professor')->value('category'));

        $this->actingAs($hr)->from('/designations/create')->post(route('designations.store'), ['name' => 'Boss', 'category' => 'boss', 'status' => 'active'])->assertSessionHasErrors('category');
    }

    // ---------------------------------------------------------------- pay grades

    /** TC-UNI-006: The payroll officer manages pay grades, with sensible limits; HR can only look */
    public function test_pay_grade_management(): void
    {
        $officer = $this->userWithRole('payroll_officer');
        $payload = ['grade_no' => 9, 'name' => 'Grade 9', 'min_basic' => 22000, 'max_basic' => 53060, 'increment_percent' => 5, 'status' => 'active'];

        $this->actingAs($officer)->post(route('salary.grades.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pay_grades', ['grade_no' => 9]);

        $this->actingAs($officer)->from('/salary/grades/create')->post(route('salary.grades.store'), $payload)->assertSessionHasErrors('grade_no');
        $this->actingAs($officer)->from('/salary/grades/create')->post(route('salary.grades.store'), array_merge($payload, ['grade_no' => 10, 'min_basic' => 30000, 'max_basic' => 20000]))->assertSessionHasErrors('max_basic');
        $this->actingAs($officer)->post(route('salary.grades.store'), ['grade_no' => 1, 'name' => 'Grade 1', 'min_basic' => 78000, 'increment_percent' => 0, 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertNull(PayGrade::where('grade_no', 1)->value('max_basic'));

        $hr = $this->userWithRole('hr_manager');
        $this->actingAs($hr)->get('/salary/grades')->assertOk();
        $this->actingAs($hr)->post(route('salary.grades.store'), array_merge($payload, ['grade_no' => 11]))->assertForbidden();
    }

    /** TC-UNI-007: A grade's range cannot be narrowed so that people placed in it fall outside */
    public function test_grade_range_must_keep_its_people_inside(): void
    {
        $grade = $this->grade();
        $this->makeEmployee(['pay_grade_id' => $grade->id, 'basic_salary' => 40000]);

        $this->actingAs($this->userWithRole('payroll_officer'))->put(route('salary.grades.update', $grade), [
            'grade_no' => 9, 'name' => 'Grade 9', 'min_basic' => 45000, 'max_basic' => 53060, 'increment_percent' => 5, 'status' => 'active',
        ])->assertSessionHas('error');

        $this->assertMoney(22000, $grade->fresh()->min_basic);
    }

    /** TC-UNI-008: An employee's basic salary must be inside the pay grade */
    public function test_basic_salary_must_fit_the_grade(): void
    {
        $grade = $this->grade();
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->post(route('employees.store'), $this->employeePayload(['pay_grade_id' => $grade->id, 'basic_salary' => 30000]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', ['email' => 'rahim.uddin@example.test', 'pay_grade_id' => $grade->id]);

        $this->actingAs($hr)->from('/employees/create')->post(route('employees.store'), $this->employeePayload(['email' => 'low@example.test', 'nid' => '1990123450001', 'pay_grade_id' => $grade->id, 'basic_salary' => 20000]))->assertSessionHasErrors('basic_salary');
        $this->actingAs($hr)->from('/employees/create')->post(route('employees.store'), $this->employeePayload(['email' => 'high@example.test', 'nid' => '1990123450002', 'pay_grade_id' => $grade->id, 'basic_salary' => 60000]))->assertSessionHasErrors('basic_salary');
    }

    /** TC-UNI-009: A fixed scale accepts only its one amount */
    public function test_fixed_scale(): void
    {
        $grade = $this->grade(1, 78000, null);
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->from('/employees/create')->post(route('employees.store'), $this->employeePayload(['pay_grade_id' => $grade->id, 'basic_salary' => 80000]))->assertSessionHasErrors('basic_salary');
        $this->actingAs($hr)->post(route('employees.store'), $this->employeePayload(['pay_grade_id' => $grade->id, 'basic_salary' => 78000]))->assertSessionHasNoErrors();
    }

    /** TC-UNI-010: HR cannot move someone to a grade that does not fit the salary on file */
    public function test_changing_the_grade_checks_the_salary_on_file(): void
    {
        $nine = $this->grade(9, 22000, 53060);
        $six = $this->grade(6, 35500, 67010);
        $employee = $this->makeEmployee(['pay_grade_id' => $nine->id, 'basic_salary' => 30000]);

        $this->actingAs($this->userWithRole('hr_manager'))->from('/employees')
            ->put(route('employees.update', $employee), $this->editPayload($employee, ['pay_grade_id' => $six->id]))
            ->assertSessionHasErrors('pay_grade_id');

        $this->assertSame($nine->id, $employee->fresh()->pay_grade_id);
    }

    /** TC-UNI-011: A basic salary revision must stay inside the grade */
    public function test_salary_revision_must_fit_the_grade(): void
    {
        $grade = $this->grade();
        $employee = $this->makeEmployee(['pay_grade_id' => $grade->id, 'basic_salary' => 30000]);
        $officer = $this->userWithRole('payroll_officer');

        $this->actingAs($officer)->post(route('salary.basic', $employee), ['basic_salary' => 90000, 'reason' => 'Test'])->assertSessionHas('error');
        $this->assertMoney(30000, $employee->fresh()->basic_salary);

        $this->actingAs($officer)->post(route('salary.basic', $employee), ['basic_salary' => 33000, 'reason' => 'Promotion'])->assertSessionHasNoErrors();
        $this->assertMoney(33000, $employee->fresh()->basic_salary);
    }

    // ---------------------------------------------------------------- employee fields

    /** TC-UNI-012: The TIN has 12 digits and belongs to one person */
    public function test_tin_rules(): void
    {
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->post(route('employees.store'), $this->employeePayload(['tin' => '123456789012']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', ['tin' => '123456789012']);

        $this->actingAs($hr)->from('/employees/create')->post(route('employees.store'), $this->employeePayload(['email' => 'b@example.test', 'nid' => '1990123450003', 'tin' => '12345678901']))->assertSessionHasErrors('tin');
        $this->actingAs($hr)->from('/employees/create')->post(route('employees.store'), $this->employeePayload(['email' => 'c@example.test', 'nid' => '1990123450004', 'tin' => '123456789012']))->assertSessionHasErrors('tin');
    }

    /** TC-UNI-013: Visiting teachers and master-roll workers can be added */
    public function test_university_employment_types(): void
    {
        $hr = $this->userWithRole('hr_manager');

        foreach (['visiting' => 1, 'master_roll' => 2] as $type => $n) {
            $this->actingAs($hr)->post(route('employees.store'), $this->employeePayload([
                'employment_type' => $type, 'email' => "{$type}@example.test", 'nid' => '19901234500' . $n . '0',
            ]))->assertSessionHasNoErrors();
        }

        $this->assertDatabaseHas('employees', ['employment_type' => 'visiting']);
        $this->assertDatabaseHas('employees', ['employment_type' => 'master_roll']);
    }

    /** TC-UNI-014: New employee IDs use the prefix from Settings */
    public function test_employee_id_prefix(): void
    {
        $this->makeEmployee();
        Setting::put('employee_code_prefix', 'HSTU');

        $this->actingAs($this->userWithRole('hr_manager'))->post(route('employees.store'), $this->employeePayload())->assertSessionHasNoErrors();

        $code = Employee::where('email', 'rahim.uddin@example.test')->value('employee_code');
        $this->assertStringStartsWith('HSTU-', $code);
        $this->assertMatchesRegularExpression('/^HSTU-\d{4}$/', $code);
    }

    /** TC-UNI-015: The prefix, the short name and the system title are saved from Settings, and the prefix is validated */
    public function test_branding_settings(): void
    {
        $admin = $this->userWithRole('admin');
        $payload = ['company_name' => 'Hajee Mohammad Danesh Science and Technology University', 'company_short_name' => 'HSTU', 'system_title' => 'HSTU Payroll Management System', 'employee_code_prefix' => 'HSTU'];

        $this->actingAs($admin)->from('/settings')->post(route('settings.company'), array_merge($payload, ['employee_code_prefix' => 'hstu 1']))->assertSessionHasErrors('employee_code_prefix');

        $this->actingAs($admin)->post(route('settings.company'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('HSTU', Setting::get('company_short_name'));
        $this->assertSame('HSTU Payroll Management System', Setting::get('system_title'));
    }

    /** TC-UNI-016: The names from Settings appear on the login page and in the signed-in pages */
    public function test_branding_is_shown(): void
    {
        Setting::put('company_short_name', 'HSTU');
        Setting::put('system_title', 'HSTU Payroll Management System');

        $this->get('/login')->assertOk()->assertSee('HSTU Payroll Management System');

        $this->actingAs($this->userWithRole('admin'))->get('/dashboard')->assertOk()->assertSee('HSTU Payroll Management System')->assertSee('HSTU');
    }

    // ---------------------------------------------------------------- bulk items

    /** TC-UNI-017: A festival bonus of 100% of basic salary is added for every teacher at once */
    public function test_bulk_festival_bonus_for_one_category(): void
    {
        $teacherPost = $this->designation('Professor', 'teacher');
        $staffPost = $this->designation('Driver', 'staff');
        $t1 = $this->makeEmployee(['designation_id' => $teacherPost->id, 'basic_salary' => 60000]);
        $t2 = $this->makeEmployee(['designation_id' => $teacherPost->id, 'basic_salary' => 40000]);
        $s1 = $this->makeEmployee(['designation_id' => $staffPost->id, 'basic_salary' => 10000]);
        $officer = $this->userWithRole('payroll_officer');
        $period = $this->makePeriod($officer);

        $this->actingAs($officer)->post(route('salary.adjustments.bulk'), [
            'payroll_period_id' => $period->id, 'salary_component_id' => SalaryComponent::where('code', 'FBONUS')->value('id'),
            'basis' => 'percent_of_basic', 'value' => 100, 'category' => 'teacher', 'note' => 'Eid festival bonus',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, PayrollAdjustment::count());
        $this->assertMoney(60000, PayrollAdjustment::where('employee_id', $t1->id)->value('amount'));
        $this->assertMoney(40000, PayrollAdjustment::where('employee_id', $t2->id)->value('amount'));
        $this->assertSame(0, PayrollAdjustment::where('employee_id', $s1->id)->count());
        $this->assertDatabaseHas('audit_logs', ['module' => 'payroll', 'action' => 'adjustment_added']);
    }

    /** TC-UNI-018: Pressing the button twice adds nothing the second time */
    public function test_bulk_item_is_not_added_twice(): void
    {
        $this->makeEmployee();
        $this->makeEmployee();
        $officer = $this->userWithRole('payroll_officer');
        $period = $this->makePeriod($officer);
        $payload = ['payroll_period_id' => $period->id, 'salary_component_id' => SalaryComponent::where('code', 'BONUS')->value('id'), 'basis' => 'fixed', 'value' => 2000];

        $this->actingAs($officer)->post(route('salary.adjustments.bulk'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($officer)->post(route('salary.adjustments.bulk'), $payload)->assertSessionHas('error');

        $this->assertSame(2, PayrollAdjustment::count());
    }

    /** TC-UNI-019: The bulk item can be limited to one department */
    public function test_bulk_item_for_one_department(): void
    {
        $physics = Department::create(['name' => 'Physics', 'type' => 'academic', 'status' => 'active']);
        $in = $this->makeEmployee(['department_id' => $physics->id]);
        $out = $this->makeEmployee();
        $officer = $this->userWithRole('payroll_officer');
        $period = $this->makePeriod($officer);

        $this->actingAs($officer)->post(route('salary.adjustments.bulk'), [
            'payroll_period_id' => $period->id, 'salary_component_id' => SalaryComponent::where('code', 'BONUS')->value('id'),
            'basis' => 'fixed', 'value' => 1500, 'department_id' => $physics->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, PayrollAdjustment::where('employee_id', $in->id)->count());
        $this->assertSame(0, PayrollAdjustment::where('employee_id', $out->id)->count());
    }

    /** TC-UNI-020: Bulk items are refused after review, for non-payroll roles, and for an absurd percentage */
    public function test_bulk_item_limits(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $officer = $this->userWithRole('payroll_officer');
        $payload = ['payroll_period_id' => $period->id, 'salary_component_id' => SalaryComponent::where('code', 'BONUS')->value('id'), 'basis' => 'percent_of_basic', 'value' => 100];

        $this->actingAs($this->userWithRole('hr_manager'))->post(route('salary.adjustments.bulk'), $payload)->assertForbidden();
        $this->actingAs($officer)->post(route('salary.adjustments.bulk'), array_merge($payload, ['value' => 600]))->assertSessionHas('error');

        $this->processPayroll($period, $admin);
        $this->actingAs($admin)->post(route('payroll.review', $period));
        $this->actingAs($officer)->post(route('salary.adjustments.bulk'), $payload)->assertSessionHas('error');

        $this->assertSame(0, PayrollAdjustment::count());
    }

    /** TC-UNI-021: A bulk festival bonus flows into payroll */
    public function test_bulk_bonus_reaches_the_salary(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();   // basic 50,000, allowances 15,000

        $this->actingAs($this->userWithRole('payroll_officer'))->post(route('salary.adjustments.bulk'), [
            'payroll_period_id' => $period->id, 'salary_component_id' => SalaryComponent::where('code', 'FBONUS')->value('id'),
            'basis' => 'percent_of_basic', 'value' => 100,
        ])->assertSessionHasNoErrors();

        $this->processPayroll($period, $admin);

        $payroll = Payroll::firstOrFail();
        $this->assertMoney(50000, $payroll->bonus);
        $this->assertMoney(115000, $payroll->net_salary);
    }

    // ---------------------------------------------------------------- annual increment

    /** TC-UNI-022: The increment page shows the proposal and applying it changes the basic salary and the history */
    public function test_annual_increment(): void
    {
        $grade = $this->grade();
        $employee = $this->makeEmployee(['pay_grade_id' => $grade->id, 'basic_salary' => 40000]);
        $officer = $this->userWithRole('payroll_officer');

        $this->actingAs($officer)->get(route('salary.increments.index'))->assertOk()->assertSee($employee->full_name)->assertSee('42,000.00');

        $this->actingAs($officer)->post(route('salary.increments.apply'), ['ids' => [$employee->id], 'reason' => 'Annual increment July'])->assertSessionHasNoErrors();

        $employee->refresh();
        $this->assertMoney(42000, $employee->basic_salary);
        $this->assertSame(today()->toDateString(), $employee->last_increment_on->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'salary_changed', 'record_id' => $employee->id]);
    }

    /** TC-UNI-023: Only one increment in 12 months */
    public function test_one_increment_a_year(): void
    {
        $grade = $this->grade();
        $employee = $this->makeEmployee(['pay_grade_id' => $grade->id, 'basic_salary' => 40000]);
        $officer = $this->userWithRole('payroll_officer');
        $payload = ['ids' => [$employee->id], 'reason' => 'Annual increment'];

        $this->actingAs($officer)->post(route('salary.increments.apply'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($officer)->post(route('salary.increments.apply'), $payload)->assertSessionHas('error');

        $this->assertMoney(42000, $employee->fresh()->basic_salary);

        // Twelve months later it is allowed again.
        $employee->update(['last_increment_on' => today()->subYear()->subDay()]);
        $this->actingAs($officer)->post(route('salary.increments.apply'), $payload)->assertSessionHasNoErrors();
        $this->assertMoney(44100, $employee->fresh()->basic_salary);
    }

    /** TC-UNI-024: The new basic salary never goes above the top of the grade */
    public function test_increment_is_capped_by_the_grade(): void
    {
        $grade = $this->grade(9, 22000, 53060);
        $near = $this->makeEmployee(['pay_grade_id' => $grade->id, 'basic_salary' => 52000]);
        $top = $this->makeEmployee(['pay_grade_id' => $grade->id, 'basic_salary' => 53060]);
        $officer = $this->userWithRole('payroll_officer');

        $this->actingAs($officer)->post(route('salary.increments.apply'), ['ids' => [$near->id, $top->id], 'reason' => 'Annual increment'])->assertSessionHasNoErrors();

        $this->assertMoney(53060, $near->fresh()->basic_salary);
        $this->assertMoney(53060, $top->fresh()->basic_salary);
        $this->assertNull($top->fresh()->last_increment_on);
    }

    /** TC-UNI-025: People without a pay grade are not on the increment page, and only payroll staff open it */
    public function test_increment_access_and_scope(): void
    {
        $withGrade = $this->makeEmployee(['pay_grade_id' => $this->grade()->id, 'basic_salary' => 30000]);
        $without = $this->makeEmployee(['basic_salary' => 30000]);

        $this->actingAs($this->userWithRole('payroll_officer'))->get(route('salary.increments.index'))
            ->assertOk()->assertSee($withGrade->full_name)->assertDontSee($without->full_name);

        $this->actingAs($this->userWithRole('hr_manager'))->get(route('salary.increments.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('hr_manager'))->post(route('salary.increments.apply'), ['ids' => [$withGrade->id], 'reason' => 'x'])->assertForbidden();
        $this->actingAs($this->userWithRole('employee', $this->makeEmployee()))->get(route('salary.increments.index'))->assertForbidden();
    }

    // ---------------------------------------------------------------- reports

    /** TC-UNI-026: Faculty, category and faculty payroll reports add up */
    public function test_university_reports(): void
    {
        $faculty = Faculty::create(['name' => 'Faculty of Engineering', 'status' => 'active']);
        $civil = Department::create(['name' => 'Civil Engineering', 'faculty_id' => $faculty->id, 'type' => 'academic', 'status' => 'active']);
        $office = Department::create(['name' => "Registrar's Office", 'type' => 'administrative', 'status' => 'active']);
        $teacher = $this->designation('Lecturer', 'teacher');
        $officer = $this->designation('Section Officer', 'officer');

        [$admin, , $period] = $this->payrollScenario();
        $this->makeEmployee(['department_id' => $civil->id, 'designation_id' => $teacher->id, 'basic_salary' => 30000]);
        $this->makeEmployee(['department_id' => $office->id, 'designation_id' => $officer->id, 'basic_salary' => 25000]);
        $this->processPayroll($period, $admin);

        $service = app(ReportService::class);

        $byFaculty = collect($service->run('employees.faculty', [])['rows']);
        $this->assertSame(1, $byFaculty->firstWhere('department', 'Civil Engineering')['total']);
        $this->assertSame('Administrative offices', $byFaculty->firstWhere('department', "Registrar's Office")['faculty']);

        $byCategory = collect($service->run('employees.category', [])['rows']);
        $this->assertSame(1, $byCategory->firstWhere('category', 'Teacher')['total']);
        $this->assertSame(1, $byCategory->firstWhere('category', 'Officer')['total']);

        $monthly = $service->run('payroll.monthly', ['period' => $period->id]);
        $facultyPayroll = $service->run('payroll.faculty', ['period' => $period->id]);
        $this->assertMoney($monthly['totals']['net'], $facultyPayroll['totals']['net']);
        $this->assertContains('Faculty of Engineering', array_column($facultyPayroll['rows'], 'faculty'));
    }

    // ---------------------------------------------------------------- the university starting data

    /** TC-UNI-027: The university seeder sets the names, structure, pay grades, leave types and weekly offs, and can run twice */
    public function test_university_seeder(): void
    {
        $this->seed(UniversitySeeder::class);

        $this->assertSame('HSTU', Setting::get('company_short_name'));
        $this->assertSame('HSTU Payroll Management System', Setting::get('system_title'));
        $this->assertSame('Hajee Mohammad Danesh Science and Technology University', Setting::get('company_name'));
        $this->assertSame('5,6', Setting::get('weekly_off_days'));
        $this->assertSame(20, PayGrade::count());
        $this->assertSame('teacher', Designation::where('name', 'Professor')->value('category'));
        $this->assertSame('officer', Designation::where('name', 'Registrar')->value('category'));
        $this->assertSame(180, (int) LeaveType::where('name', 'Maternity Leave')->value('days_per_year'));
        $this->assertFalse((bool) LeaveType::where('name', 'Leave Without Pay')->value('is_paid'));
        $this->assertNotNull(Department::where('name', 'Computer Science and Engineering')->value('faculty_id'));
        $this->assertNull(Department::where('name', "Registrar's Office")->value('faculty_id'));

        $faculties = Faculty::count();
        $departments = Department::count();
        $this->seed(UniversitySeeder::class);

        $this->assertSame($faculties, Faculty::count());
        $this->assertSame($departments, Department::count());
        $this->assertSame(20, PayGrade::count());
    }

    /** TC-UNI-028: The demonstration people are consistent: salaries inside their grades, accounts linked */
    public function test_university_demo_data_is_consistent(): void
    {
        $this->seed(UniversitySeeder::class);
        $this->seed(UniversityDemoSeeder::class);

        $this->assertSame(22, Employee::count());

        foreach (Employee::with('payGrade')->get() as $employee) {
            $this->assertStringStartsWith('HSTU-', $employee->employee_code);
            if ($employee->payGrade) {
                $this->assertTrue($employee->payGrade->allows((float) $employee->basic_salary), "{$employee->employee_code} basic salary is outside {$employee->payGrade->label}");
            }
        }

        $hr = User::where('email', 'abdur.rahman@hstu.example')->firstOrFail();
        $this->assertSame('hr_manager', $hr->role->name);
        $this->assertSame('Registrar', $hr->employee->designation->name);
        $this->assertSame('admin', User::where('email', 'admin@hstu.example')->firstOrFail()->role->name);
    }

    /** TC-UNI-029: Payroll can be run for the demonstration data without any error */
    public function test_payroll_runs_for_the_demo_data(): void
    {
        $this->seed(UniversitySeeder::class);
        $this->seed(UniversityDemoSeeder::class);

        $admin = User::where('email', 'admin@hstu.example')->firstOrFail();
        $last = Carbon::now()->subMonthNoOverflow();
        $period = $this->makePeriod($admin, $last->year, $last->month);

        $summary = app(PayrollService::class)->process($period, $admin);

        $this->assertSame(22, $summary['count']);
        $this->assertGreaterThan(0, $summary['net']);
        $this->assertSame(0, Payroll::where('net_salary', '<=', 0)->count());
    }

    /** TC-UNI-030: A university salary: House Rent 50%, Medical 1,500 and a 10% provident fund; absence is charged on 30 days */
    public function test_university_salary_calculation(): void
    {
        $this->seed(UniversitySeeder::class);
        TaxSlab::query()->delete();

        $admin = $this->userWithRole('admin');
        $employee = $this->makeEmployee(['basic_salary' => 40000]);
        foreach (['HRA', 'MED', 'PF'] as $code) {
            $this->addToSalary($employee, $code);
        }
        $period = $this->makePeriod($admin);

        $this->processPayroll($period, $admin);

        // House rent 20,000 + medical 1,500 = 21,500 allowances; gross 61,500; provident fund 4,000.
        $payroll = Payroll::firstOrFail();
        $this->assertMoney(21500, $payroll->total_allowances);
        $this->assertMoney(61500, $payroll->gross_salary);
        $this->assertMoney(4000, $payroll->total_deductions);
        $this->assertMoney(57500, $payroll->net_salary);

        // One absent day: (basic + allowances) / 30 = 2,050.
        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-09-01', 'status' => 'absent', 'working_hours' => 0]);
        $this->processPayroll($period, $admin);

        $this->assertMoney(55450, Payroll::firstOrFail()->net_salary);
    }

    // ---------------------------------------------------------------- installation and slips

    /** TC-UNI-031: The university install creates the administrator and the HSTU data, without demonstration people */
    public function test_university_install(): void
    {
        $this->artisan('pms:install', ['--university' => true, '--name' => 'Registrar Office Admin', '--email' => 'admin@hstu.example', '--password' => 'Admin12345'])->assertExitCode(0);

        $this->assertSame('HSTU', Setting::get('company_short_name'));
        $this->assertSame('admin', User::where('email', 'admin@hstu.example')->firstOrFail()->role->name);
        $this->assertSame(20, PayGrade::count());
        $this->assertSame(0, Employee::count());
    }

    /** TC-UNI-032: The university install with --demo also loads the demonstration people */
    public function test_university_install_with_demo(): void
    {
        $this->artisan('pms:install', ['--university' => true, '--demo' => true, '--name' => 'Registrar Office Admin', '--email' => 'boss@hstu.example', '--password' => 'Admin12345'])->assertExitCode(0);

        $this->assertSame(22, Employee::count());
        $this->assertNotNull(User::where('email', 'tanvir.ahmed@hstu.example')->first());
    }

    /** TC-UNI-033: The salary slip shows the pay grade and the provident fund account */
    public function test_slip_shows_grade_and_pf_account(): void
    {
        [$admin, $employee, $period] = $this->payrollScenario();
        $employee->update(['pay_grade_id' => $this->grade()->id, 'pf_account_no' => 'CPF-0007']);
        $this->approvePayroll($period, $admin);

        $this->actingAs($this->userWithRole('payroll_officer'))->get(route('payslips.show', Payroll::firstOrFail()))
            ->assertOk()->assertSee('Grade 9')->assertSee('CPF-0007');
    }
}
