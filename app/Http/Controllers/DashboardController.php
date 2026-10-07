<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user()->load('role', 'employee.department', 'employee.designation');

        // Each card is added only if the user's role allows that information.
        $cards = [];

        if ($user->hasPermission('employees.view')) {
            $cards[] = ['label' => 'Total Employees', 'value' => Employee::count(), 'icon' => 'bi-people', 'tone' => 'primary'];
            $cards[] = ['label' => 'Active Employees', 'value' => Employee::active()->count(), 'icon' => 'bi-person-check', 'tone' => 'success'];
        }

        if ($user->hasPermission('departments.view')) {
            $cards[] = ['label' => 'Departments', 'value' => Department::active()->count(), 'icon' => 'bi-diagram-3', 'tone' => 'info'];
        }

        if ($user->hasPermission('attendance.view')) {
            $present = Attendance::whereDate('date', today())
                ->whereIn('status', ['present', 'late', 'half_day'])
                ->count();
            $cards[] = ['label' => 'Present Today', 'value' => $present, 'icon' => 'bi-calendar-check', 'tone' => 'secondary'];
        }

        if ($user->hasPermission('leave.view')) {
            $cards[] = [
                'label' => 'Pending Leave Requests',
                'value' => LeaveRequest::where('status', 'pending')->count(),
                'icon' => 'bi-hourglass-split',
                'tone' => 'warning',
            ];
        }

        return view('dashboard', [
            'user' => $user,
            'cards' => $cards,
        ]);
    }
}
