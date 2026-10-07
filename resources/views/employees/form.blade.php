@extends('layouts.app')

@section('title', $employee->exists ? 'Edit Employee' : 'Add Employee')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">Please correct the highlighted fields and try again.</div>
    @endif

    <form method="POST" enctype="multipart/form-data" novalidate
          action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">
        @csrf
        @if ($employee->exists) @method('PUT') @endif

        <div class="card mb-3">
            <div class="card-header">Personal information</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Employee ID</label>
                        <input type="text" class="form-control" value="{{ $nextCode }}" disabled>
                        @unless ($employee->exists) <div class="form-text">Assigned automatically when saved.</div> @endunless
                    </div>
                    <x-form-input name="first_name" label="First name" :value="$employee->first_name" required maxlength="60" col="4" />
                    <x-form-input name="last_name" label="Last name" :value="$employee->last_name" required maxlength="60" col="4" />
                    <x-form-select name="gender" label="Gender" :options="\App\Models\Employee::GENDERS" :value="$employee->gender" required col="4" />
                    <x-form-input name="date_of_birth" label="Date of birth" type="date" :value="$employee->date_of_birth?->format('Y-m-d')" required col="4" />
                    <x-form-input name="nid" label="NID number" :value="$employee->nid" required hint="10, 13 or 17 digits" maxlength="17" col="4" />
                    <div class="col-md-6">
                        <label for="photo" class="form-label">Photo</label>
                        <div class="d-flex align-items-center gap-3">
                            @if ($employee->photo_url)
                                <img src="{{ $employee->photo_url }}" alt="" class="rounded-circle" width="48" height="48" style="object-fit: cover;">
                            @endif
                            <input type="file" id="photo" name="photo" accept="image/png,image/jpeg"
                                   class="form-control @error('photo') is-invalid @enderror">
                        </div>
                        @error('photo') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        <div class="form-text">JPG or PNG, up to 2 MB.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Contact details</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form-input name="email" label="Email" type="email" :value="$employee->email" required maxlength="120" />
                    <x-form-input name="phone" label="Mobile number" :value="$employee->phone" required placeholder="01712345678" maxlength="16" />
                    <x-form-input name="address" label="Address" :value="$employee->address" maxlength="255" col="12" />
                    <x-form-input name="emergency_contact_name" label="Emergency contact name" :value="$employee->emergency_contact_name" maxlength="100" />
                    <x-form-input name="emergency_contact_phone" label="Emergency contact number" :value="$employee->emergency_contact_phone" placeholder="01812345678" maxlength="16" />
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Employment</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form-select name="department_id" label="Department" :options="$departments" :value="$employee->department_id" required col="4" />
                    <x-form-select name="designation_id" label="Designation" :options="$designations" :value="$employee->designation_id" required col="4" />
                    <x-form-select name="employment_type" label="Employment type" :options="\App\Models\Employee::EMPLOYMENT_TYPES" :value="$employee->employment_type" required col="4" />
                    <x-form-input name="joining_date" label="Date of joining" type="date" :value="$employee->joining_date?->format('Y-m-d')" required col="4" />
                    @if ($employee->exists)
                        <x-form-select name="status" label="Status" :options="\App\Models\Employee::STATUSES" :value="$employee->status" required col="4" />
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Salary and bank</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-form-input name="basic_salary" label="Basic salary (৳)" type="number" step="0.01" min="0" :value="$employee->basic_salary" required col="4" />
                    <x-form-input name="bank_name" label="Bank name" :value="$employee->bank_name" maxlength="100" col="4" />
                    <x-form-input name="bank_branch" label="Branch" :value="$employee->bank_branch" maxlength="100" col="4" />
                    <x-form-input name="bank_account_number" label="Account number" :value="$employee->bank_account_number" maxlength="20" col="4" />
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary">{{ $employee->exists ? 'Save changes' : 'Add employee' }}</button>
            <a href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}" class="btn btn-light">Cancel</a>
        </div>
    </form>
@endsection
