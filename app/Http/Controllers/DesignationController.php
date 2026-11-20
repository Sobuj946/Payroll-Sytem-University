<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DesignationController extends Controller
{
    public function index(Request $request)
    {
        $designations = Designation::withCount('employees')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%' . trim($request->q) . '%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('designations.index', compact('designations'));
    }

    public function create()
    {
        return view('designations.form', ['designation' => new Designation(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $designation = Designation::create($this->validated($request));

        AuditService::log('created', 'designations', "Designation {$designation->name} was created", $designation->id);

        return redirect()->route('designations.index')
            ->with('success', "Designation \"{$designation->name}\" has been added.");
    }

    public function show(Designation $designation)
    {
        $employees = $designation->employees()->with('department')->orderBy('first_name')->get();

        return view('designations.show', compact('designation', 'employees'));
    }

    public function edit(Designation $designation)
    {
        return view('designations.form', compact('designation'));
    }

    public function update(Request $request, Designation $designation)
    {
        $designation->update($this->validated($request, $designation));

        AuditService::log('updated', 'designations', "Designation {$designation->name} was updated", $designation->id);

        return redirect()->route('designations.index')
            ->with('success', "Designation \"{$designation->name}\" has been updated.");
    }

    public function toggleStatus(Designation $designation)
    {
        if ($designation->status === 'active') {
            $activeEmployees = $designation->employees()->where('status', 'active')->count();

            if ($activeEmployees > 0) {
                return back()->with('error', "\"{$designation->name}\" cannot be deactivated while {$activeEmployees} active employee(s) belong to it.");
            }

            $designation->update(['status' => 'inactive']);
            $word = 'deactivated';
        } else {
            $designation->update(['status' => 'active']);
            $word = 'activated';
        }

        AuditService::log($word, 'designations', "Designation {$designation->name} was {$word}", $designation->id);

        return back()->with('success', "Designation \"{$designation->name}\" has been {$word}.");
    }

    private function validated(Request $request, ?Designation $designation = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('designations', 'name')->ignore($designation?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'name.required' => 'Please enter the designation name.',
            'name.unique' => 'A designation with this name already exists.',
            'name.max' => 'The designation name may not be longer than 100 characters.',
            'description.max' => 'The description may not be longer than 500 characters.',
            'status.required' => 'Please choose a status.',
        ]);
    }
}
