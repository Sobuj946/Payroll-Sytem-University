@extends('layouts.app')

@section('title', $faculty->exists ? 'Edit Faculty' : 'Add Faculty')

@section('content')
    <div class="row">
        <div class="col-lg-7 col-xl-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $faculty->exists ? route('faculties.update', $faculty) : route('faculties.store') }}">
                        @csrf
                        @if ($faculty->exists) @method('PUT') @endif

                        <div class="mb-3">
                            <label for="name" class="form-label">Faculty name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name', $faculty->name) }}" maxlength="120"
                                   class="form-control @error('name') is-invalid @enderror" required autofocus>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="short_name" class="form-label">Short name</label>
                            <input type="text" id="short_name" name="short_name" value="{{ old('short_name', $faculty->short_name) }}" maxlength="20"
                                   class="form-control @error('short_name') is-invalid @enderror" placeholder="e.g. Agriculture">
                            @error('short_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active" @selected(old('status', $faculty->status) === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $faculty->status) === 'inactive')>Inactive</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ $faculty->exists ? 'Save changes' : 'Add faculty' }}</button>
                            <a href="{{ route('faculties.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
