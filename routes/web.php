<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\MyLeaveController;
use App\Http\Controllers\PayrollAdjustmentController;
use App\Http\Controllers\PayrollPeriodController;
use App\Http\Controllers\SalaryComponentController;
use App\Http\Controllers\SalaryController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Everything below needs a signed-in, active account.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/account/password', [AccountController::class, 'editPassword'])->name('account.password');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    // ----- Phase 5: organization -----
    Route::middleware('permission:departments.view')->group(function () {
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    });
    Route::middleware('permission:departments.manage')->group(function () {
        Route::get('departments/create', [DepartmentController::class, 'create'])->name('departments.create');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::patch('departments/{department}/status', [DepartmentController::class, 'toggleStatus'])->name('departments.status');
    });
    Route::get('departments/{department}', [DepartmentController::class, 'show'])
        ->middleware('permission:departments.view')->name('departments.show');

    Route::middleware('permission:designations.view')->group(function () {
        Route::get('designations', [DesignationController::class, 'index'])->name('designations.index');
    });
    Route::middleware('permission:designations.manage')->group(function () {
        Route::get('designations/create', [DesignationController::class, 'create'])->name('designations.create');
        Route::post('designations', [DesignationController::class, 'store'])->name('designations.store');
        Route::get('designations/{designation}/edit', [DesignationController::class, 'edit'])->name('designations.edit');
        Route::put('designations/{designation}', [DesignationController::class, 'update'])->name('designations.update');
        Route::patch('designations/{designation}/status', [DesignationController::class, 'toggleStatus'])->name('designations.status');
    });
    Route::get('designations/{designation}', [DesignationController::class, 'show'])
        ->middleware('permission:designations.view')->name('designations.show');

    Route::middleware('permission:employees.view')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    });
    Route::middleware('permission:employees.manage')->group(function () {
        Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    });
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])
        ->middleware('permission:employees.view')->name('employees.show');

    // ----- Phase 6: attendance -----
    Route::middleware('permission:attendance.view')->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance/monthly', [AttendanceController::class, 'monthly'])->name('attendance.monthly');
        Route::get('attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');
        Route::get('attendance/employee/{employee}', [AttendanceController::class, 'employee'])->name('attendance.employee');
    });
    Route::post('attendance', [AttendanceController::class, 'store'])
        ->middleware('permission:attendance.manage')->name('attendance.store');

    // The signed-in employee's own records
    Route::get('my/attendance', [AttendanceController::class, 'mine'])
        ->middleware('permission:self.access')->name('my.attendance');

    // ----- Phase 7: leave -----
    Route::middleware('permission:leave.view')->group(function () {
        Route::get('leave', [LeaveRequestController::class, 'index'])->name('leave.index');
        Route::get('leave/balances', [LeaveRequestController::class, 'balances'])->name('leave.balances');
    });
    Route::middleware('permission:leave.manage')->group(function () {
        Route::post('leave/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave.approve');
        Route::post('leave/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave.reject');
    });

    Route::middleware('permission:leave.types')->group(function () {
        Route::get('leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::get('leave-types/create', [LeaveTypeController::class, 'create'])->name('leave-types.create');
        Route::post('leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
        Route::get('leave-types/{leaveType}/edit', [LeaveTypeController::class, 'edit'])->name('leave-types.edit');
        Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave-types.update');
        Route::patch('leave-types/{leaveType}/status', [LeaveTypeController::class, 'toggleStatus'])->name('leave-types.status');
    });

    // The signed-in employee's own leave
    Route::middleware('permission:self.access')->group(function () {
        Route::get('my/leave', [MyLeaveController::class, 'index'])->name('my.leave');
        Route::get('my/leave/create', [MyLeaveController::class, 'create'])->name('my.leave.create');
        Route::post('my/leave', [MyLeaveController::class, 'store'])->name('my.leave.store');
        Route::post('my/leave/{leaveRequest}/cancel', [MyLeaveController::class, 'cancel'])->name('my.leave.cancel');
    });

    // ----- Phase 8: salary -----
    Route::middleware('permission:salary.view')->group(function () {
        Route::get('salary', [SalaryController::class, 'index'])->name('salary.index');
        Route::get('salary/components', [SalaryComponentController::class, 'index'])->name('salary.components.index');
    });
    Route::middleware('permission:salary.manage')->group(function () {
        Route::get('salary/components/create', [SalaryComponentController::class, 'create'])->name('salary.components.create');
        Route::post('salary/components', [SalaryComponentController::class, 'store'])->name('salary.components.store');
        Route::get('salary/components/{component}/edit', [SalaryComponentController::class, 'edit'])->name('salary.components.edit');
        Route::put('salary/components/{component}', [SalaryComponentController::class, 'update'])->name('salary.components.update');
        Route::patch('salary/components/{component}/status', [SalaryComponentController::class, 'toggleStatus'])->name('salary.components.status');

        Route::post('salary/employees/{employee}/basic', [SalaryController::class, 'updateBasic'])->name('salary.basic');
        Route::post('salary/employees/{employee}/components', [SalaryController::class, 'assign'])->name('salary.assign');
        Route::post('salary/employees/{employee}/components/{employeeSalaryComponent}/revise', [SalaryController::class, 'revise'])->name('salary.revise');
        Route::post('salary/employees/{employee}/components/{employeeSalaryComponent}/end', [SalaryController::class, 'end'])->name('salary.end');
    });
    Route::get('salary/employees/{employee}', [SalaryController::class, 'show'])
        ->middleware('permission:salary.view')->name('salary.employee');

    // Overtime, bonus, loan instalments ... entered per payroll period
    Route::get('salary/adjustments', [PayrollAdjustmentController::class, 'index'])
        ->middleware('permission:payroll.view')->name('salary.adjustments.index');
    Route::middleware('permission:payroll.process')->group(function () {
        Route::post('salary/adjustments', [PayrollAdjustmentController::class, 'store'])->name('salary.adjustments.store');
        Route::delete('salary/adjustments/{adjustment}', [PayrollAdjustmentController::class, 'destroy'])->name('salary.adjustments.destroy');
        Route::post('payroll/periods', [PayrollPeriodController::class, 'store'])->name('payroll.periods.store');
    });

    // Later phases add their routes here, each with ->middleware('permission:...')
});

require __DIR__ . '/auth.php';
