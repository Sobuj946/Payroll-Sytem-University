<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Faculty;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $departments = Department::with('faculty')->withCount('employees')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%' . trim($request->q) . '%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('faculty_id'), fn ($query) => $query->where('faculty_id', $request->faculty_id))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('departments.index', ['departments' => $departments, 'faculties' => Faculty::orderBy('name')->get(['id', 'name'])]);
    }

    public function create()
    {
        return view('departments.form', $this->formData(new Department(['status' => 'active', 'type' => 'academic'])));
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
        return view('departments.form', $this->formData($department));
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
            'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            'type' => ['required', Rule::in(array_keys(Department::TYPES))],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'type.required' => 'Please choose whether this is an academic department or an administrative office.',
            'name.required' => 'Please enter the department name.',
            'name.unique' => 'A department with this name already exists.',
            'name.max' => 'The department name may not be longer than 100 characters.',
            'description.max' => 'The description may not be longer than 500 characters.',
            'status.required' => 'Please choose a status.',
        ]);
    }

    private function formData(Department $department): array
    {
        return [
            'department' => $department,
            // Only active faculties, plus the one this department already belongs to.
            'faculties' => Faculty::where('status', 'active')->orWhere('id', $department->faculty_id)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
