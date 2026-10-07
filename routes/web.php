<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\EmployeeController;
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

    // Later phases add their routes here, each with ->middleware('permission:...')
});

require __DIR__ . '/auth.php';
