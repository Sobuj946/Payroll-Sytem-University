@extends('layouts.app')

@section('title', 'Request Leave')

@section('content')
    <div class="row">
        <div class="col-lg-7 col-xl-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('my.leave.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="leave_type_id" class="form-label">Leave type <span class="text-danger">*</span></label>
                            <select id="leave_type_id" name="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
                                <option value="">Select...</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->id }}" @selected((string) old('leave_type_id') === (string) $type->id)>
                                        {{ $type->name }}@isset($available[$type->id]) ({{ max(0, $available[$type->id] + 0) }} days available)@endisset
                                        @unless ($type->is_paid) - unpaid @endunless
                                    </option>
                                @endforeach
                            </select>
                            @error('leave_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">First day <span class="text-danger">*</span></label>
                                <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}"
                                       class="form-control @error('start_date') is-invalid @enderror" required>
                                @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">Last day <span class="text-danger">*</span></label>
                                <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}"
                                       class="form-control @error('end_date') is-invalid @enderror" required>
                                @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input @error('half_day') is-invalid @enderror" type="checkbox" id="half_day" name="half_day" value="1" @checked(old('half_day'))>
                            <label class="form-check-label" for="half_day">Half day (only for a single day)</label>
                            @error('half_day') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea id="reason" name="reason" rows="3" maxlength="500"
                                      class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                            @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Weekly offs and holidays inside the dates are not counted as leave days.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Send request</button>
                            <a href="{{ route('my.leave') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
