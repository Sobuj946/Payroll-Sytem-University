<?php

namespace App\Http\Requests;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    // Bangladesh mobile number: 01XXXXXXXXX, optionally with +88 / 88 in front.
    private const PHONE_PATTERN = '/^(?:\+?88)?01[3-9]\d{8}$/';

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('employees.manage');
    }

    protected function prepareForValidation(): void
    {
        foreach (['phone', 'emergency_contact_phone'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => preg_replace('/[\s\-]/', '', (string) $this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:120', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'phone' => ['required', 'regex:' . self::PHONE_PATTERN],
            'address' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::in(array_keys(Employee::GENDERS))],
            'date_of_birth' => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->toDateString()],
            'nid' => ['required', 'regex:/^(\d{10}|\d{13}|\d{17})$/', Rule::unique('employees', 'nid')->ignore($employee?->id)],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'regex:' . self::PHONE_PATTERN],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'designation_id' => ['required', 'integer', 'exists:designations,id'],
            'employment_type' => ['required', Rule::in(array_keys(Employee::EMPLOYMENT_TYPES))],
            'joining_date' => ['required', 'date', 'after:date_of_birth'],
            // Changing an existing employee's salary needs the salary.manage permission (HR can only add a starting salary).
            'basic_salary' => [
                $employee && ! $this->user()?->hasPermission('salary.manage') ? 'nullable' : 'required',
                'numeric', 'min:0', 'max:99999999',
            ],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'required_with:bank_name', 'regex:/^[0-9]{8,20}$/'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'status' => [$employee ? 'required' : 'nullable', Rule::in(array_keys(Employee::STATUSES))],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Please enter the first name.',
            'last_name.required' => 'Please enter the last name.',
            'email.required' => 'Please enter an email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'Another employee already uses this email address.',
            'phone.required' => 'Please enter a mobile number.',
            'phone.regex' => 'Enter a valid Bangladesh mobile number, for example 01712345678.',
            'gender.required' => 'Please choose a gender.',
            'date_of_birth.required' => 'Please enter the date of birth.',
            'date_of_birth.before_or_equal' => 'The employee must be at least 18 years old.',
            'nid.required' => 'Please enter the NID number.',
            'nid.regex' => 'The NID must have 10, 13 or 17 digits.',
            'nid.unique' => 'Another employee already has this NID number.',
            'emergency_contact_phone.regex' => 'Enter a valid Bangladesh mobile number for the emergency contact.',
            'photo.image' => 'The photo must be an image file.',
            'photo.mimes' => 'The photo must be a JPG or PNG file.',
            'photo.max' => 'The photo may not be larger than 2 MB.',
            'department_id.required' => 'Please choose a department.',
            'designation_id.required' => 'Please choose a designation.',
            'employment_type.required' => 'Please choose an employment type.',
            'joining_date.required' => 'Please enter the date of joining.',
            'joining_date.after' => 'The joining date must be after the date of birth.',
            'basic_salary.required' => 'Please enter the basic salary.',
            'basic_salary.numeric' => 'The basic salary must be a number.',
            'basic_salary.min' => 'The basic salary cannot be negative.',
            'bank_account_number.required_with' => 'Please enter the bank account number.',
            'bank_account_number.regex' => 'The bank account number must contain 8 to 20 digits only.',
            'status.required' => 'Please choose a status.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $checks = [
                ['department_id', Department::class, 'department'],
                ['designation_id', Designation::class, 'designation'],
            ];

            foreach ($checks as [$field, $model, $label]) {
                $id = $this->input($field);

                if (! $id || $validator->errors()->has($field)) {
                    continue;
                }

                // An inactive department/designation is only accepted if the employee already has it.
                $current = $this->route('employee')?->{$field};

                if ((int) $id !== (int) $current && $model::where('id', $id)->where('status', 'active')->doesntExist()) {
                    $validator->errors()->add($field, "The selected {$label} is not active.");
                }
            }
        });
    }
}
