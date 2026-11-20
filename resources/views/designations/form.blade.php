@extends('layouts.app')

@section('title', $designation->exists ? 'Edit Designation' : 'Add Designation')

@section('content')
    <div class="row">
        <div class="col-lg-7 col-xl-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $designation->exists ? route('designations.update', $designation) : route('designations.store') }}">
                        @csrf
                        @if ($designation->exists) @method('PUT') @endif

                        <div class="mb-3">
                            <label for="name" class="form-label">Designation name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name', $designation->name) }}" maxlength="100"
                                   class="form-control @error('name') is-invalid @enderror" required autofocus>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" rows="3" maxlength="500"
                                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $designation->description) }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active" @selected(old('status', $designation->status) === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $designation->status) === 'inactive')>Inactive</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ $designation->exists ? 'Save changes' : 'Add designation' }}</button>
                            <a href="{{ route('designations.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
