<?php

namespace App\Http\Requests;

use App\Models\LeaveType;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only users linked to an employee record can ask for leave.
        return (bool) $this->user()?->employee_id;
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('status', 'active')],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . today()->subDays(30)->toDateString()],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'half_day' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'Please choose a leave type.',
            'leave_type_id.exists' => 'The selected leave type is not available.',
            'start_date.required' => 'Please choose the first day of leave.',
            'start_date.after_or_equal' => 'Leave can be requested for the last 30 days at most.',
            'end_date.required' => 'Please choose the last day of leave.',
            'end_date.after_or_equal' => 'The last day cannot be earlier than the first day.',
            'reason.required' => 'Please give a short reason for the leave.',
            'reason.min' => 'Please give a little more detail in the reason.',
            'reason.max' => 'The reason may not be longer than 500 characters.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $service = app(LeaveService::class);
            $employee = $this->user()->employee;
            $type = LeaveType::find($this->input('leave_type_id'));
            $start = Carbon::createFromFormat('!Y-m-d', $this->input('start_date'));
            $end = Carbon::createFromFormat('!Y-m-d', $this->input('end_date'));
            $half = $this->boolean('half_day');

            if ($start->year !== $end->year) {
                $validator->errors()->add('end_date', 'A request cannot cover two calendar years. Please submit one request for each year.');
                return;
            }

            if ($start->lt($employee->joining_date)) {
                $validator->errors()->add('start_date', 'Leave cannot start before your joining date.');
                return;
            }

            if ($half && ! $start->isSameDay($end)) {
                $validator->errors()->add('half_day', 'A half-day request must be for a single day.');
                return;
            }

            $days = $service->daysBetween($start, $end, $half);

            if ($days <= 0) {
                $validator->errors()->add('start_date', 'The chosen dates are all weekly offs or holidays, so no leave is needed.');
                return;
            }

            if ($service->hasOverlap($employee->id, $start, $end)) {
                $validator->errors()->add('start_date', 'These dates overlap with another pending or approved leave request.');
                return;
            }

            if ($service->isLimited($type)) {
                $left = $service->available($employee, $type, $start->year);

                if ($left < $days) {
                    $validator->errors()->add('leave_type_id', "You have only " . max(0, $left + 0) . " day(s) of {$type->name} available, but this request needs " . ($days + 0) . '.');
                }
            }
        });
    }
}
