<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $departments = Department::withCount('employees')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%' . trim($request->q) . '%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        return view('departments.form', ['department' => new Department(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $department = Department::create($this->validated($request));

        AuditService::log('created', 'departments', "Department {$department->name} was created", $department->id);

        return redirect()->route('departments.index')
            ->with('success', "Department \"{$department->name}\" has been added.");
    }

    public function show(Department $department)
    {
        $employees = $department->employees()->with('designation')->orderBy('first_name')->get();

        return view('departments.show', compact('department', 'employees'));
    }

    public function edit(Department $department)
    {
        return view('departments.form', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $department->update($this->validated($request, $department));

        AuditService::log('updated', 'departments', "Department {$department->name} was updated", $department->id);

        return redirect()->route('departments.index')
            ->with('success', "Department \"{$department->name}\" has been updated.");
    }

    public function toggleStatus(Department $department)
    {
        if ($department->status === 'active') {
            $activeEmployees = $department->employees()->where('status', 'active')->count();

            if ($activeEmployees > 0) {
                return back()->with('error', "\"{$department->name}\" cannot be deactivated while {$activeEmployees} active employee(s) belong to it.");
            }

            $department->update(['status' => 'inactive']);
            $word = 'deactivated';
        } else {
            $department->update(['status' => 'active']);
            $word = 'activated';
        }

        AuditService::log($word, 'departments', "Department {$department->name} was {$word}", $department->id);

        return back()->with('success', "Department \"{$department->name}\" has been {$word}.");
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'name.required' => 'Please enter the department name.',
            'name.unique' => 'A department with this name already exists.',
            'name.max' => 'The department name may not be longer than 100 characters.',
            'description.max' => 'The description may not be longer than 500 characters.',
            'status.required' => 'Please choose a status.',
        ]);
    }
}
