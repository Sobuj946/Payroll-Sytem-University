@extends('layouts.app')

@section('title', $grade->exists ? 'Edit Pay Grade' : 'Add Pay Grade')

@section('content')
    <div class="row">
        <div class="col-lg-7 col-xl-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $grade->exists ? route('salary.grades.update', $grade) : route('salary.grades.store') }}">
                        @csrf
                        @if ($grade->exists) @method('PUT') @endif

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="grade_no" class="form-label">Grade number <span class="text-danger">*</span></label>
                                <input type="number" id="grade_no" name="grade_no" value="{{ old('grade_no', $grade->grade_no) }}" min="1" max="50"
                                       class="form-control @error('grade_no') is-invalid @enderror" required autofocus>
                                @error('grade_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-8">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" value="{{ old('name', $grade->name) }}" maxlength="60"
                                       class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Grade 9" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="min_basic" class="form-label">Starting basic salary (৳) <span class="text-danger">*</span></label>
                                <input type="number" id="min_basic" name="min_basic" value="{{ old('min_basic', $grade->min_basic) }}" step="0.01" min="0"
                                       class="form-control @error('min_basic') is-invalid @enderror" required>
                                @error('min_basic') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="max_basic" class="form-label">Highest basic salary (৳)</label>
                                <input type="number" id="max_basic" name="max_basic" value="{{ old('max_basic', $grade->max_basic) }}" step="0.01" min="0"
                                       class="form-control @error('max_basic') is-invalid @enderror">
                                @error('max_basic') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Leave empty for a fixed scale.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="increment_percent" class="form-label">Yearly increment (%) <span class="text-danger">*</span></label>
                                <input type="number" id="increment_percent" name="increment_percent" value="{{ old('increment_percent', $grade->increment_percent) }}" step="0.01" min="0" max="50"
                                       class="form-control @error('increment_percent') is-invalid @enderror" required>
                                @error('increment_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="active" @selected(old('status', $grade->status) === 'active')>Active</option>
                                    <option value="inactive" @selected(old('status', $grade->status) === 'inactive')>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ $grade->exists ? 'Save changes' : 'Add grade' }}</button>
                            <a href="{{ route('salary.grades.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
