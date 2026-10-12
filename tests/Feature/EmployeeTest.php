<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Rahim',
            'last_name' => 'Uddin',
            'email' => 'rahim.uddin@example.test',
            'phone' => '01712345678',
            'address' => 'Mirpur, Dhaka',
            'gender' => 'male',
            'date_of_birth' => '1993-04-12',
            'nid' => '1993123456789',
            'department_id' => Department::orderBy('id')->value('id'),
            'designation_id' => Designation::orderBy('id')->value('id'),
            'employment_type' => 'permanent',
            'joining_date' => '2024-01-10',
            'basic_salary' => 45000,
        ], $overrides);
    }

    /** The form values of an existing employee, as the edit form would send them. */
    private function editPayload(Employee $employee, array $overrides = []): array
    {
        return array_merge([
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'gender' => $employee->gender,
            'date_of_birth' => $employee->date_of_birth->format('Y-m-d'),
            'nid' => $employee->nid,
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'employment_type' => $employee->employment_type,
            'joining_date' => $employee->joining_date->format('Y-m-d'),
            'basic_salary' => $employee->basic_salary,
            'status' => $employee->status,
        ], $overrides);
    }

    /** TC-EMP-001: Create employee with valid information */
    public function test_create_employee_with_valid_information(): void
    {
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->post(route('employees.store'), $this->payload())->assertSessionHasNoErrors();

        $employee = Employee::where('email', 'rahim.uddin@example.test')->first();
        $this->assertNotNull($employee);
        $this->assertStringStartsWith('EMP-', $employee->employee_code);
        $this->assertSame('active', $employee->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'module' => 'employees', 'record_id' => $employee->id]);
    }

    /** TC-EMP-002: Create employee with a duplicate email */
    public function test_duplicate_email_is_rejected(): void
    {
        $this->makeEmployee(['email' => 'rahim.uddin@example.test']);

        $this->actingAs($this->userWithRole('hr_manager'))->from('/employees/create')
            ->post(route('employees.store'), $this->payload(['nid' => '1993123450000']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Employee::count());
    }

    /** TC-EMP-003: Create employee with a duplicate NID */
    public function test_duplicate_nid_is_rejected(): void
    {
        $this->makeEmployee(['nid' => '1993123456789']);

        $this->actingAs($this->userWithRole('hr_manager'))->from('/employees/create')
            ->post(route('employees.store'), $this->payload())
            ->assertSessionHasErrors('nid');
    }

    /** TC-EMP-004: A mobile number that is not a Bangladesh number is rejected */
    public function test_invalid_phone_is_rejected(): void
    {
        $hr = $this->userWithRole('hr_manager');

        foreach (['12345', '02123456789', '0171234567', 'abcdefghijk'] as $phone) {
            $this->actingAs($hr)->from('/employees/create')
                ->post(route('employees.store'), $this->payload(['phone' => $phone]))
                ->assertSessionHasErrors('phone');
        }

        // With spaces, dashes and the country code the number is accepted.
        $this->actingAs($hr)->post(route('employees.store'), $this->payload(['phone' => '+880 1712-345678']))
            ->assertSessionHasNoErrors();
    }

    /** TC-EMP-005: An employee under 18 is rejected */
    public function test_underage_employee_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('hr_manager'))->from('/employees/create')
            ->post(route('employees.store'), $this->payload(['date_of_birth' => now()->subYears(16)->toDateString()]))
            ->assertSessionHasErrors('date_of_birth');
    }

    /** TC-EMP-006: NID must have 10, 13 or 17 digits */
    public function test_nid_format_is_checked(): void
    {
        $this->actingAs($this->userWithRole('hr_manager'))->from('/employees/create')
            ->post(route('employees.store'), $this->payload(['nid' => '12345']))
            ->assertSessionHasErrors('nid');
    }

    /** TC-EMP-007: A negative salary and a missing department are rejected */
    public function test_salary_and_department_are_required(): void
    {
        $this->actingAs($this->userWithRole('hr_manager'))->from('/employees/create')
            ->post(route('employees.store'), $this->payload(['basic_salary' => -5, 'department_id' => '']))
            ->assertSessionHasErrors(['basic_salary', 'department_id']);
    }

    /** TC-EMP-008: A photo that is not an image is rejected */
    public function test_photo_must_be_an_image(): void
    {
        $this->actingAs($this->userWithRole('hr_manager'))->from('/employees/create')
            ->post(route('employees.store'), $this->payload(['photo' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors('photo');
    }

    /** TC-EMP-009: A valid photo is stored under a random name and removed when replaced */
    public function test_photo_is_stored_and_replaced(): void
    {
        $hr = $this->userWithRole('hr_manager');
        $created = [];

        try {
            $this->actingAs($hr)->post(route('employees.store'), $this->payload(['photo' => $this->pngFile('me.png')]))
                ->assertSessionHasNoErrors();

            $employee = Employee::where('email', 'rahim.uddin@example.test')->firstOrFail();
            $first = $employee->photo;
            $created[] = $first;
            $this->assertNotNull($first);
            $this->assertNotSame('me.jpg', $first);
            $this->assertFileExists(public_path('uploads/employees/' . $first));

            $this->actingAs($hr)->put(route('employees.update', $employee), $this->editPayload($employee, ['photo' => $this->pngFile('new.png')]))
                ->assertSessionHasNoErrors();

            $second = $employee->fresh()->photo;
            $created[] = $second;
            $this->assertNotSame($first, $second);
            $this->assertFileDoesNotExist(public_path('uploads/employees/' . $first));
        } finally {
            foreach ($created as $name) {
                @unlink(public_path('uploads/employees/' . $name));
            }
        }
    }

    /** TC-EMP-010: HR cannot change an existing employee's salary through the edit form */
    public function test_hr_cannot_change_salary_on_edit(): void
    {
        $employee = $this->makeEmployee(['basic_salary' => 50000]);

        $this->actingAs($this->userWithRole('hr_manager'))
            ->put(route('employees.update', $employee), $this->editPayload($employee, ['basic_salary' => 90000, 'last_name' => 'Changed']))
            ->assertSessionHasNoErrors();

        $employee->refresh();
        $this->assertSame('Changed', $employee->last_name);
        $this->assertMoney(50000, $employee->basic_salary);
    }

    /** TC-EMP-011: The administrator can change the salary and the change is recorded */
    public function test_admin_salary_change_is_audited(): void
    {
        $employee = $this->makeEmployee(['basic_salary' => 50000]);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('employees.update', $employee), $this->editPayload($employee, ['basic_salary' => 60000]))
            ->assertSessionHasNoErrors();

        $this->assertMoney(60000, $employee->fresh()->basic_salary);
        $this->assertDatabaseHas('audit_logs', ['action' => 'salary_changed', 'module' => 'employees', 'record_id' => $employee->id]);
    }

    /** TC-EMP-012: Employees can be searched and filtered */
    public function test_employee_list_search_and_filter(): void
    {
        $this->makeEmployee(['first_name' => 'Nusrat', 'last_name' => 'Jahan']);
        $this->makeEmployee(['first_name' => 'Tanvir', 'last_name' => 'Ahmed', 'status' => 'resigned']);
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->get(route('employees.index', ['q' => 'Nusrat']))->assertSee('Nusrat Jahan')->assertDontSee('Tanvir Ahmed');
        $this->actingAs($hr)->get(route('employees.index', ['status' => 'resigned']))->assertSee('Tanvir Ahmed')->assertDontSee('Nusrat Jahan');
    }

    /** TC-EMP-013: Employees cannot be deleted, only given a status such as Resigned */
    public function test_employees_cannot_be_deleted(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($this->userWithRole('admin'))->delete('/employees/' . $employee->id)->assertStatus(405);

        $this->assertNotNull(Employee::find($employee->id));
    }

    /** TC-DEP-001: Department names must be unique */
    public function test_duplicate_department_name_is_rejected(): void
    {
        $existing = Department::orderBy('id')->value('name');

        $this->actingAs($this->userWithRole('hr_manager'))->from('/departments/create')
            ->post(route('departments.store'), ['name' => $existing, 'status' => 'active'])
            ->assertSessionHasErrors('name');
    }

    /** TC-DEP-002: A department with active employees cannot be deactivated */
    public function test_department_with_active_employees_cannot_be_deactivated(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($this->userWithRole('hr_manager'))
            ->patch(route('departments.status', $employee->department_id))
            ->assertSessionHas('error');

        $this->assertSame('active', Department::find($employee->department_id)->status);
    }

    /** TC-DEP-003: An empty department can be deactivated and activated again */
    public function test_empty_department_can_be_deactivated(): void
    {
        $department = Department::create(['name' => 'Legal', 'status' => 'active']);
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->patch(route('departments.status', $department));
        $this->assertSame('inactive', $department->fresh()->status);

        $this->actingAs($hr)->patch(route('departments.status', $department));
        $this->assertSame('active', $department->fresh()->status);
    }

    /** TC-DEP-004: An employee cannot be placed in an inactive department */
    public function test_inactive_department_cannot_receive_employees(): void
    {
        $department = Department::create(['name' => 'Archive', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole('hr_manager'))->from('/employees/create')
            ->post(route('employees.store'), $this->payload(['department_id' => $department->id]))
            ->assertSessionHasErrors('department_id');
    }
}
