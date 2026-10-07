@extends('layouts.app')

@section('title', $type->exists ? 'Edit Leave Type' : 'Add Leave Type')

@section('content')
    <div class="row">
        <div class="col-lg-7 col-xl-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $type->exists ? route('leave-types.update', $type) : route('leave-types.store') }}">
                        @csrf
                        @if ($type->exists) @method('PUT') @endif

                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name', $type->name) }}" maxlength="60"
                                   class="form-control @error('name') is-invalid @enderror" required autofocus>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="days_per_year" class="form-label">Days per year <span class="text-danger">*</span></label>
                            <input type="number" id="days_per_year" name="days_per_year" value="{{ old('days_per_year', $type->days_per_year) }}" min="0" max="365"
                                   class="form-control @error('days_per_year') is-invalid @enderror" required>
                            @error('days_per_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Enter 0 for no yearly limit.</div>
                        </div>

                        <div class="mb-3">
                            <label for="is_paid" class="form-label">Pay <span class="text-danger">*</span></label>
                            <select id="is_paid" name="is_paid" class="form-select @error('is_paid') is-invalid @enderror" required>
                                <option value="1" @selected((string) old('is_paid', (int) $type->is_paid) === '1')>Paid leave</option>
                                <option value="0" @selected((string) old('is_paid', (int) $type->is_paid) === '0')>Unpaid leave (may reduce salary)</option>
                            </select>
                            @error('is_paid') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active" @selected(old('status', $type->status) === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $type->status) === 'inactive')>Inactive</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ $type->exists ? 'Save changes' : 'Add leave type' }}</button>
                            <a href="{{ route('leave-types.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
