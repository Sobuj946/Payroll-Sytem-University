<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TaxSlab;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    private function newUser(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Farhana Akter',
            'email' => 'farhana@example.test',
            'role_id' => Role::where('name', 'payroll_officer')->value('id'),
            'status' => 'active',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ], $overrides);
    }

    // ---------------- users ----------------

    /** TC-ADM-001: The administrator creates a user */
    public function test_admin_creates_a_user(): void
    {
        $this->actingAs($this->userWithRole('admin'))->post(route('users.store'), $this->newUser())->assertSessionHasNoErrors();

        $user = User::where('email', 'farhana@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Secret123', $user->password));
        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'action' => 'created', 'record_id' => $user->id]);
    }

    /** TC-ADM-002: A weak password or a duplicate email is refused */
    public function test_user_validation(): void
    {
        $this->userWithRole('hr_manager');
        $admin = $this->userWithRole('admin');
        $existing = User::where('role_id', Role::where('name', 'hr_manager')->value('id'))->first();

        $this->actingAs($admin)->from('/users/create')->post(route('users.store'), $this->newUser(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');
        $this->actingAs($admin)->from('/users/create')->post(route('users.store'), $this->newUser(['email' => $existing->email]))
            ->assertSessionHasErrors('email');
    }

    /** TC-ADM-003: An Employee account must be linked to an employee, and each employee gets one account */
    public function test_employee_role_needs_a_linked_employee(): void
    {
        $admin = $this->userWithRole('admin');
        $employee = $this->makeEmployee();
        $role = Role::where('name', 'employee')->value('id');

        $this->actingAs($admin)->post(route('users.store'), $this->newUser(['role_id' => $role]))->assertSessionHas('error');
        $this->assertDatabaseMissing('users', ['email' => 'farhana@example.test']);

        $this->actingAs($admin)->post(route('users.store'), $this->newUser(['role_id' => $role, 'employee_id' => $employee->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'farhana@example.test', 'employee_id' => $employee->id]);

        $this->actingAs($admin)->from('/users/create')
            ->post(route('users.store'), $this->newUser(['email' => 'second@example.test', 'role_id' => $role, 'employee_id' => $employee->id]))
            ->assertSessionHasErrors('employee_id');
    }

    /** TC-ADM-004: An administrator cannot deactivate or demote themselves */
    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = $this->userWithRole('admin');
        $hrRole = Role::where('name', 'hr_manager')->value('id');

        $this->actingAs($admin)->patch(route('users.status', $admin))->assertSessionHas('error');
        $this->assertSame('active', $admin->fresh()->status);

        $this->actingAs($admin)->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role_id' => $hrRole, 'status' => 'active'])
            ->assertSessionHas('error');
        $this->assertSame('admin', $admin->fresh()->role->name);
    }

    /** TC-ADM-005: Deactivating a user is recorded and signs them out of the system */
    public function test_deactivating_a_user(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('hr_manager');

        $this->actingAs($admin)->patch(route('users.status', $target))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $target->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'action' => 'deactivated', 'record_id' => $target->id]);

        $this->actingAs($target->fresh())->get('/dashboard')->assertRedirect(route('login'));
    }

    /** TC-ADM-006: The administrator sets a new password for someone */
    public function test_admin_resets_a_password(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('hr_manager');

        $this->actingAs($admin)->post(route('users.password', $target), ['password' => 'Brand9New', 'password_confirmation' => 'Brand9New'])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Brand9New', $target->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'action' => 'password_reset', 'record_id' => $target->id]);
    }

    /** TC-ADM-007: Changing a role is recorded */
    public function test_role_change_is_audited(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('hr_manager');

        $this->actingAs($admin)->put(route('users.update', $target), [
            'name' => $target->name, 'email' => $target->email,
            'role_id' => Role::where('name', 'payroll_officer')->value('id'), 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertSame('payroll_officer', $target->fresh()->role->name);
        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'action' => 'role_changed', 'record_id' => $target->id]);
    }

    // ---------------- roles and permissions ----------------

    /** TC-ADM-008: Permissions can be changed and take effect on the next request */
    public function test_permission_change_takes_effect(): void
    {
        $admin = $this->userWithRole('admin');
        $hr = $this->userWithRole('hr_manager');
        $hrRole = Role::where('name', 'hr_manager')->firstOrFail();

        $this->actingAs(User::find($hr->id))->get(route('reports.show', 'payroll.monthly'))->assertForbidden();

        $ids = $hrRole->permissions->pluck('id')->push(Permission::where('key', 'reports.payroll')->value('id'))->all();
        $this->actingAs($admin)->post(route('roles.update'), ['permissions' => [$hrRole->id => $ids]])->assertRedirect();

        $this->actingAs(User::find($hr->id))->get(route('reports.show', 'payroll.monthly'))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['module' => 'roles', 'action' => 'permissions_changed']);
    }

    /** TC-ADM-009: User, settings and backup permissions can never be given to another role */
    public function test_administration_permissions_are_reserved(): void
    {
        $admin = $this->userWithRole('admin');
        $hr = $this->userWithRole('hr_manager');
        $hrRole = Role::where('name', 'hr_manager')->firstOrFail();

        $ids = $hrRole->permissions->pluck('id')
            ->merge(Permission::whereIn('key', ['users.manage', 'settings.manage', 'backup.manage'])->pluck('id'))->all();
        $this->actingAs($admin)->post(route('roles.update'), ['permissions' => [$hrRole->id => $ids]])->assertRedirect();

        $keys = $hrRole->fresh()->permissions->pluck('key')->all();
        $this->assertNotContains('users.manage', $keys);
        $this->assertNotContains('settings.manage', $keys);
        $this->assertNotContains('backup.manage', $keys);

        $this->actingAs(User::find($hr->id))->get('/users')->assertForbidden();
    }

    /** TC-ADM-010: Every role keeps access to its own pages, and the admin role keeps everything */
    public function test_self_access_and_admin_permissions_cannot_be_removed(): void
    {
        $admin = $this->userWithRole('admin');
        $total = Permission::count();

        $this->actingAs($admin)->post(route('roles.update'), ['permissions' => []])->assertRedirect();

        $employeeRole = Role::where('name', 'employee')->firstOrFail();
        $this->assertContains('self.access', $employeeRole->fresh()->permissions->pluck('key')->all());
        $this->assertSame($total, Role::where('name', 'admin')->firstOrFail()->permissions()->count());
    }

    // ---------------- settings ----------------

    /** TC-ADM-011: Company details can be saved and a logo uploaded */
    public function test_company_details_and_logo(): void
    {
        $admin = $this->userWithRole('admin');
        $logo = null;

        try {
            $this->actingAs($admin)->post(route('settings.company'), [
                'company_name' => 'Rupali Traders Ltd.',
                'company_address' => 'Motijheel, Dhaka',
                'company_phone' => '+880 2-9876543',
                'company_email' => 'hr@rupali.example',
                'logo' => $this->pngFile('logo.png'),
            ])->assertSessionHasNoErrors();

            $this->assertSame('Rupali Traders Ltd.', Setting::get('company_name'));

            $logo = Setting::get('company_logo');
            $this->assertNotEmpty($logo);
            $this->assertFileExists(public_path('uploads/' . $logo));
        } finally {
            if ($logo) {
                @unlink(public_path('uploads/' . $logo));
            }
        }
    }

    /** TC-ADM-012: A logo that is not an image is refused */
    public function test_logo_must_be_an_image(): void
    {
        $this->actingAs($this->userWithRole('admin'))->from('/settings')->post(route('settings.company'), [
            'company_name' => 'Rupali Traders Ltd.',
            'logo' => UploadedFile::fake()->create('logo.php', 10, 'text/x-php'),
        ])->assertSessionHasErrors('logo');
    }

    /** TC-ADM-013: Attendance rules are validated and saved */
    public function test_attendance_rules(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->from('/settings')->post(route('settings.attendance'), ['office_start_time' => '9am', 'late_grace_minutes' => 10])
            ->assertSessionHasErrors('office_start_time');

        $this->actingAs($admin)->post(route('settings.attendance'), ['weekly_off_days' => [5, 6], 'office_start_time' => '09:30', 'late_grace_minutes' => 15])
            ->assertSessionHasNoErrors();

        $this->assertSame('5,6', Setting::get('weekly_off_days'));
        $this->assertSame('09:30', Setting::get('office_start_time'));
    }

    /** TC-ADM-014: Payroll rules are validated and saved */
    public function test_payroll_rules(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->from('/settings')->post(route('settings.payroll'), [
            'working_day_basis' => 'weekly', 'fixed_working_days' => 30, 'deduction_base' => 'gross', 'late_days_per_deduction' => 0,
        ])->assertSessionHasErrors('working_day_basis');

        $this->actingAs($admin)->post(route('settings.payroll'), [
            'working_day_basis' => 'fixed', 'fixed_working_days' => 26, 'deduction_base' => 'basic', 'late_days_per_deduction' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertSame('fixed', Setting::get('working_day_basis'));
        $this->assertSame('26', Setting::get('fixed_working_days'));
        $this->assertSame('3', Setting::get('late_days_per_deduction'));
    }

    /** TC-ADM-015: Tax slabs of one set cannot overlap */
    public function test_tax_slabs_cannot_overlap(): void
    {
        $admin = $this->userWithRole('admin');
        $slab = fn ($from, $to, $rate) => ['effective_from' => '2027-01-01', 'from_amount' => $from, 'to_amount' => $to, 'rate_percent' => $rate];

        $this->actingAs($admin)->post(route('settings.slabs.store'), $slab(0, 400000, 5))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('settings.slabs.store'), $slab(300000, null, 10))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('settings.slabs.store'), $slab(400000, null, 10))->assertSessionHasNoErrors();

        $this->assertSame(2, TaxSlab::whereDate('effective_from', '2027-01-01')->count());
    }

    /** TC-ADM-016: A tax slab must end above where it starts and the rate must be 0 to 100 */
    public function test_tax_slab_validation(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->from('/settings')->post(route('settings.slabs.store'), ['effective_from' => '2027-01-01', 'from_amount' => 500, 'to_amount' => 100, 'rate_percent' => 5])
            ->assertSessionHasErrors('to_amount');
        $this->actingAs($admin)->from('/settings')->post(route('settings.slabs.store'), ['effective_from' => '2027-01-01', 'from_amount' => 0, 'to_amount' => 100, 'rate_percent' => 150])
            ->assertSessionHasErrors('rate_percent');
    }

    /** TC-ADM-017: A holiday can be added once per date and removed */
    public function test_holidays(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('settings.holidays.store'), ['date' => '2026-03-20', 'name' => 'Eid ul-Fitr'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->from('/settings')->post(route('settings.holidays.store'), ['date' => '2026-03-20', 'name' => 'Duplicate'])->assertSessionHasErrors('date');

        $holiday = Holiday::where('date', '2026-03-20')->firstOrFail();
        $this->actingAs($admin)->delete(route('settings.holidays.destroy', $holiday));
        $this->assertNull(Holiday::find($holiday->id));
    }

    // ---------------- audit log and backup ----------------

    /** TC-ADM-018: The audit log can be read and filtered but never edited or deleted */
    public function test_audit_log_is_read_only(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->post(route('departments.store'), ['name' => 'Legal Affairs', 'status' => 'active']);

        $this->actingAs($admin)->get(route('audit.index', ['module' => 'departments']))
            ->assertOk()->assertSee('Legal Affairs');

        $this->actingAs($admin)->put('/audit-logs')->assertStatus(405);
        $this->actingAs($admin)->delete('/audit-logs')->assertStatus(405);
        $this->actingAs($admin)->post('/audit-logs')->assertStatus(405);
    }

    /** TC-ADM-019: A backup is a SQL file with the tables and rows, and the download is recorded */
    public function test_backup_download(): void
    {
        $this->makeEmployee(['first_name' => 'Backup', 'last_name' => 'Person']);

        $response = $this->actingAs($this->userWithRole('admin'))->post(route('backup.download'));

        $response->assertOk();
        $sql = $response->streamedContent();
        $this->assertStringContainsString('CREATE TABLE `employees`', $sql);
        $this->assertStringContainsString('INSERT INTO `employees`', $sql);
        $this->assertStringContainsString('Backup', $sql);
        $this->assertStringNotContainsString('CREATE TABLE `sessions`', $sql);
        $this->assertDatabaseHas('audit_logs', ['module' => 'backup', 'action' => 'backup']);
    }

    /** TC-ADM-020: Only the administrator can download a backup */
    public function test_backup_is_admin_only(): void
    {
        foreach (['hr_manager', 'payroll_officer'] as $role) {
            $this->actingAs($this->userWithRole($role))->post(route('backup.download'))->assertForbidden();
        }
    }
}
