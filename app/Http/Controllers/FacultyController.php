<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Faculty;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Faculties group the academic departments of the university. Same permissions as departments. */
class FacultyController extends Controller
{
    public function index(Request $request)
    {
        $faculties = Faculty::withCount(['departments', 'departments as active_departments_count' => fn ($q) => $q->where('status', 'active')])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . trim($request->q) . '%'))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('faculties.index', compact('faculties'));
    }

    public function create()
    {
        return view('faculties.form', ['faculty' => new Faculty(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $faculty = Faculty::create($this->validated($request));

        AuditService::log('created', 'faculties', "Faculty {$faculty->name} was created", $faculty->id);

        return redirect()->route('faculties.index')->with('success', "\"{$faculty->name}\" has been added.");
    }

    public function edit(Faculty $faculty)
    {
        return view('faculties.form', compact('faculty'));
    }

    public function update(Request $request, Faculty $faculty)
    {
        $faculty->update($this->validated($request, $faculty));

        AuditService::log('updated', 'faculties', "Faculty {$faculty->name} was updated", $faculty->id);

        return redirect()->route('faculties.index')->with('success', "\"{$faculty->name}\" has been updated.");
    }

    public function toggleStatus(Faculty $faculty)
    {
        if ($faculty->status === 'active') {
            $open = $faculty->departments()->where('status', 'active')->count();

            if ($open > 0) {
                throw new BusinessRuleException("\"{$faculty->name}\" cannot be deactivated while {$open} active department(s) belong to it.");
            }
        }

        $faculty->update(['status' => $faculty->status === 'active' ? 'inactive' : 'active']);
        $word = $faculty->status === 'active' ? 'activated' : 'deactivated';

        AuditService::log($word, 'faculties', "Faculty {$faculty->name} was {$word}", $faculty->id);

        return back()->with('success', "\"{$faculty->name}\" has been {$word}.");
    }

    private function validated(Request $request, ?Faculty $faculty = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('faculties', 'name')->ignore($faculty?->id)],
            'short_name' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'name.required' => 'Please enter the faculty name.',
            'name.unique' => 'A faculty with this name already exists.',
        ]);
    }
}
