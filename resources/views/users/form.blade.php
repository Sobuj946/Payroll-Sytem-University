@extends('layouts.app')

@section('title', $user->exists ? 'Edit User' : 'Add User')

@section('content')
    <div class="row g-3">
        <div class="col-lg-7 col-xl-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" autocomplete="off">
                        @csrf
                        @if ($user->exists) @method('PUT') @endif

                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="100"
                                   class="form-control @error('name') is-invalid @enderror" required autofocus>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email (used to sign in) <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="120"
                                   class="form-control @error('email') is-invalid @enderror" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                                <select id="role_id" name="role_id" class="form-select @error('role_id') is-invalid @enderror" required>
                                    <option value="">Select...</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>{{ $role->label }}</option>
                                    @endforeach
                                </select>
                                @error('role_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                                    <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option>
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Linked employee</label>
                            <select id="employee_id" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror">
                                <option value="">Not linked</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((string) old('employee_id', $user->employee_id) === (string) $employee->id)>{{ $employee->employee_code }} · {{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                            @error('employee_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Required for the Employee role. It lets the person see their own attendance, leave and salary slips.</div>
                        </div>

                        @unless ($user->exists)
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" id="password" name="password" autocomplete="new-password"
                                           class="form-control @error('password') is-invalid @enderror" required>
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="password_confirmation" class="form-label">Confirm password <span class="text-danger">*</span></label>
                                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control" required>
                                </div>
                                <div class="col-12 form-text mt-0">At least 8 characters with letters and numbers. Ask the person to change it after the first sign-in.</div>
                            </div>
                        @endunless

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ $user->exists ? 'Save changes' : 'Create account' }}</button>
                            <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($user->exists)
            <div class="col-lg-5 col-xl-4">
                <div class="card">
                    <div class="card-header">Set a new password</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('users.password', $user) }}" autocomplete="off"
                              data-confirm="Set a new password for {{ $user->name }}? They will be signed out everywhere.">
                            @csrf
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New password</label>
                                <input type="password" id="new_password" name="password" autocomplete="new-password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password_confirmation" class="form-label">Confirm new password</label>
                                <input type="password" id="new_password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100">Set password</button>
                            <div class="form-text mt-2">For someone who forgot their password. It is recorded in the audit log.</div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
