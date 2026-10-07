<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // module => [permission key => label]
        $catalog = [
            'employees' => ['employees.view' => 'View employees', 'employees.manage' => 'Add, edit and deactivate employees'],
            'departments' => ['departments.view' => 'View departments', 'departments.manage' => 'Manage departments'],
            'designations' => ['designations.view' => 'View designations', 'designations.manage' => 'Manage designations'],
            'attendance' => ['attendance.view' => 'View all attendance', 'attendance.manage' => 'Record and edit attendance'],
            'leave' => [
                'leave.view' => 'View all leave requests',
                'leave.manage' => 'Approve or reject leave requests',
                'leave.types' => 'Manage leave types',
            ],
            'salary' => ['salary.view' => 'View salary structures', 'salary.manage' => 'Manage salary structures and components'],
            'payroll' => [
                'payroll.view' => 'View payroll',
                'payroll.process' => 'Create periods, process payroll, enter adjustments',
                'payroll.review' => 'Mark payroll as reviewed',
                'payroll.approve' => 'Approve payroll and reopen locked payroll',
                'payroll.close' => 'Close payroll periods',
            ],
            'payments' => ['payments.view' => 'View payments', 'payments.manage' => 'Record salary payments'],
            'payslips' => ['payslips.view' => 'View salary slips', 'payslips.generate' => 'Generate and download salary slips'],
            'reports' => [
                'reports.employee' => 'Employee reports',
                'reports.attendance' => 'Attendance reports',
                'reports.leave' => 'Leave reports',
                'reports.payroll' => 'Payroll reports',
            ],
            'administration' => [
                'users.manage' => 'Manage users and roles',
                'audit.view' => 'View audit logs',
                'settings.manage' => 'Manage system settings',
                'backup.manage' => 'Backup and export data',
            ],
            'self_service' => ['self.access' => 'Access own profile, attendance, leave and payslips'],
        ];

        $ids = [];
        foreach ($catalog as $module => $items) {
            foreach ($items as $key => $label) {
                $ids[$key] = Permission::updateOrCreate(
                    ['key' => $key],
                    ['label' => $label, 'module' => $module]
                )->id;
            }
        }

        $roles = [
            'admin' => ['Administrator', 'Full access to every module', array_keys($ids)],
            'hr_manager' => ['HR Manager', 'Manages people, attendance and leave; can view salary and payroll', [
                'employees.view', 'employees.manage', 'departments.view', 'departments.manage',
                'designations.view', 'designations.manage', 'attendance.view', 'attendance.manage',
                'leave.view', 'leave.manage', 'leave.types', 'salary.view', 'payroll.view',
                'payslips.view', 'reports.employee', 'reports.attendance', 'reports.leave', 'self.access',
            ]],
            'payroll_officer' => ['Payroll Officer', 'Manages salary, payroll processing and payments', [
                'employees.view', 'departments.view', 'designations.view', 'attendance.view', 'leave.view',
                'salary.view', 'salary.manage', 'payroll.view', 'payroll.process', 'payroll.review',
                'payments.view', 'payments.manage', 'payslips.view', 'payslips.generate',
                'reports.payroll', 'self.access',
            ]],
            'employee' => ['Employee', 'Self-service access to own records only', ['self.access']],
        ];

        foreach ($roles as $name => [$label, $description, $keys]) {
            $role = Role::updateOrCreate(['name' => $name], ['label' => $label, 'description' => $description]);
            $role->permissions()->sync(array_map(fn ($k) => $ids[$k], $keys));
        }
    }
}
