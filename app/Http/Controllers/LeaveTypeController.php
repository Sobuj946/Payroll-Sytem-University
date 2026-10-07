<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    public function index()
    {
        return view('leave-types.index', [
            'types' => LeaveType::withCount('requests')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('leave-types.form', [
            'type' => new LeaveType(['status' => 'active', 'is_paid' => true, 'days_per_year' => 0]),
        ]);
    }

    public function store(Request $request)
    {
        $type = LeaveType::create($this->validated($request));

        AuditService::log('created', 'leave', "Leave type {$type->name} was created", $type->id);

        return redirect()->route('leave-types.index')->with('success', "Leave type \"{$type->name}\" has been added.");
    }

    public function edit(LeaveType $leaveType)
    {
        return view('leave-types.form', ['type' => $leaveType]);
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $leaveType->update($this->validated($request, $leaveType));

        AuditService::log('updated', 'leave', "Leave type {$leaveType->name} was updated", $leaveType->id);

        return redirect()->route('leave-types.index')->with('success', "Leave type \"{$leaveType->name}\" has been updated.");
    }

    public function toggleStatus(LeaveType $leaveType)
    {
        $leaveType->update(['status' => $leaveType->status === 'active' ? 'inactive' : 'active']);
        $word = $leaveType->status === 'active' ? 'activated' : 'deactivated';

        AuditService::log($word, 'leave', "Leave type {$leaveType->name} was {$word}", $leaveType->id);

        return back()->with('success', "Leave type \"{$leaveType->name}\" has been {$word}.");
    }

    private function validated(Request $request, ?LeaveType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('leave_types', 'name')->ignore($type?->id)],
            'days_per_year' => ['required', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'name.required' => 'Please enter the leave type name.',
            'name.unique' => 'A leave type with this name already exists.',
            'days_per_year.required' => 'Please enter the yearly allowance (0 for no limit).',
            'days_per_year.integer' => 'The yearly allowance must be a whole number.',
            'days_per_year.max' => 'The yearly allowance cannot be more than 365 days.',
        ]);

        return $data;
    }
}
