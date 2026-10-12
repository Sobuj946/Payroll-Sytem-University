@extends('layouts.app')

@section('title', $salaryComponent->exists ? 'Edit Component' : 'Add Component')

@section('content')
    <div class="row">
        <div class="col-lg-7 col-xl-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $salaryComponent->exists ? route('salary.components.update', $salaryComponent) : route('salary.components.store') }}">
                        @csrf
                        @if ($salaryComponent->exists) @method('PUT') @endif

                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" value="{{ old('name', $salaryComponent->name) }}" maxlength="80"
                                       class="form-control @error('name') is-invalid @enderror" required autofocus>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" id="code" name="code" value="{{ old('code', $salaryComponent->code) }}" maxlength="20"
                                       class="form-control text-uppercase @error('code') is-invalid @enderror" @disabled($salaryComponent->exists) @required(! $salaryComponent->exists)>
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if ($salaryComponent->exists) <div class="form-text">Cannot be changed.</div> @endif
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" @disabled($salaryComponent->exists)>
                                    <option value="earning" @selected(old('type', $salaryComponent->type) === 'earning')>Earning (added to salary)</option>
                                    <option value="deduction" @selected(old('type', $salaryComponent->type) === 'deduction')>Deduction (taken from salary)</option>
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="source" class="form-label">How it is used <span class="text-danger">*</span></label>
                                <select id="source" name="source" class="form-select @error('source') is-invalid @enderror" @disabled($salaryComponent->exists)>
                                    <option value="structure" @selected(old('source', $salaryComponent->source) === 'structure')>Part of the salary structure</option>
                                    <option value="adjustment" @selected(old('source', $salaryComponent->source) === 'adjustment')>Entered every month</option>
                                </select>
                                @error('source') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="calc_type" class="form-label">Calculated as <span class="text-danger">*</span></label>
                                <select id="calc_type" name="calc_type" class="form-select @error('calc_type') is-invalid @enderror" required>
                                    <option value="fixed" @selected(old('calc_type', $salaryComponent->calc_type) === 'fixed')>Fixed amount (৳)</option>
                                    <option value="percent" @selected(old('calc_type', $salaryComponent->calc_type) === 'percent')>Percentage of basic salary</option>
                                </select>
                                @error('calc_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Items entered every month are always fixed amounts.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="default_value" class="form-label">Default amount / percentage <span class="text-danger">*</span></label>
                                <input type="number" id="default_value" name="default_value" step="0.01" min="0" value="{{ old('default_value', $salaryComponent->default_value) }}"
                                       class="form-control @error('default_value') is-invalid @enderror" required>
                                @error('default_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="is_taxable" class="form-label">Counts towards taxable income</label>
                                <select id="is_taxable" name="is_taxable" class="form-select">
                                    <option value="1" @selected((string) old('is_taxable', (int) $salaryComponent->is_taxable) === '1')>Yes</option>
                                    <option value="0" @selected((string) old('is_taxable', (int) $salaryComponent->is_taxable) === '0')>No</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="active" @selected(old('status', $salaryComponent->status) === 'active')>Active</option>
                                    <option value="inactive" @selected(old('status', $salaryComponent->status) === 'inactive')>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ $salaryComponent->exists ? 'Save changes' : 'Add component' }}</button>
                            <a href="{{ route('salary.components.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
