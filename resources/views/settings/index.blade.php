@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    @php
        $tabs = ['company' => ['Company', 'bi-building'], 'attendance' => ['Attendance', 'bi-clock'], 'payroll' => ['Payroll rules', 'bi-calculator'], 'holidays' => ['Holidays', 'bi-calendar-event'], 'tax' => ['Tax slabs', 'bi-percent']];
        $dayNames = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger py-2">@foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach</div>
    @endif

    <ul class="nav nav-tabs mb-3">
        @foreach ($tabs as $key => [$label, $icon])
            <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('settings.index', ['tab' => $key]) }}"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    {{-- ============ Company ============ --}}
    @if ($tab === 'company')
        <div class="row">
            <div class="col-lg-8 col-xl-7">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted small">These details appear in the page titles, on salary slips and on printed reports.</p>
                        <form method="POST" action="{{ route('settings.company') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Organization name <span class="text-danger">*</span></label>
                                <input type="text" id="company_name" name="company_name" value="{{ old('company_name', $s['company_name']) }}" maxlength="120" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="company_address" class="form-label">Address</label>
                                <input type="text" id="company_address" name="company_address" value="{{ old('company_address', $s['company_address']) }}" maxlength="255" class="form-control">
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="company_phone" class="form-label">Phone</label>
                                    <input type="text" id="company_phone" name="company_phone" value="{{ old('company_phone', $s['company_phone']) }}" maxlength="40" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label for="company_email" class="form-label">Email</label>
                                    <input type="email" id="company_email" name="company_email" value="{{ old('company_email', $s['company_email']) }}" maxlength="120" class="form-control">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="logo" class="form-label">Logo</label>
                                @if ($s['company_logo'])
                                    <div class="mb-2"><img src="{{ asset('uploads/' . basename($s['company_logo'])) }}" alt="Current logo" style="max-height: 56px;"></div>
                                @endif
                                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg" class="form-control">
                                <div class="form-text">PNG or JPG, up to 1 MB. Shown on salary slips.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save company details</button>
                        </form>

                        @if ($s['company_logo'])
                            <form method="POST" action="{{ route('settings.logo.remove') }}" class="mt-3" data-confirm="Remove the logo?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove logo</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ Attendance ============ --}}
    @if ($tab === 'attendance')
        <div class="row">
            <div class="col-lg-7 col-xl-6">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('settings.attendance') }}">
                            @csrf
                            <div class="mb-3">
                                <div class="form-label">Weekly off days</div>
                                @foreach ($dayNames as $number => $name)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="day{{ $number }}" name="weekly_off_days[]" value="{{ $number }}"
                                               @checked(in_array($number, old('weekly_off_days', $s['weekly_off_days'])))>
                                        <label class="form-check-label" for="day{{ $number }}">{{ $name }}</label>
                                    </div>
                                @endforeach
                                <div class="form-text">These days are not counted as working days for attendance, leave and payroll.</div>
                            </div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="office_start_time" class="form-label">Office start time</label>
                                    <input type="time" id="office_start_time" name="office_start_time" value="{{ old('office_start_time', $s['office_start_time']) }}" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="late_grace_minutes" class="form-label">Grace period (minutes)</label>
                                    <input type="number" id="late_grace_minutes" name="late_grace_minutes" value="{{ old('late_grace_minutes', $s['late_grace_minutes']) }}" min="0" max="120" class="form-control" required>
                                    <div class="form-text">Arriving after start time plus this grace counts as late.</div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save attendance rules</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ Payroll ============ --}}
    @if ($tab === 'payroll')
        <div class="row">
            <div class="col-lg-8 col-xl-7">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted small">How payroll turns absence and unpaid leave into money. Changes apply the next time a payroll is processed. Approved payrolls are never changed.</p>
                        <form method="POST" action="{{ route('settings.payroll') }}">
                            @csrf
                            <div class="row g-3 mb-3">
                                <div class="col-md-7">
                                    <label for="working_day_basis" class="form-label">Daily rate is divided by</label>
                                    <select id="working_day_basis" name="working_day_basis" class="form-select">
                                        <option value="calendar_working_days" @selected(old('working_day_basis', $s['working_day_basis']) === 'calendar_working_days')>Working days of that month</option>
                                        <option value="fixed" @selected(old('working_day_basis', $s['working_day_basis']) === 'fixed')>A fixed number of days</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="fixed_working_days" class="form-label">Fixed number of days</label>
                                    <input type="number" id="fixed_working_days" name="fixed_working_days" value="{{ old('fixed_working_days', $s['fixed_working_days']) }}" min="20" max="31" class="form-control" required>
                                    <div class="form-text">Used only for "fixed".</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="deduction_base" class="form-label">Daily rate is calculated from</label>
                                <select id="deduction_base" name="deduction_base" class="form-select">
                                    <option value="gross" @selected(old('deduction_base', $s['deduction_base']) === 'gross')>Basic salary + fixed allowances</option>
                                    <option value="basic" @selected(old('deduction_base', $s['deduction_base']) === 'basic')>Basic salary only</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="late_days_per_deduction" class="form-label">Late arrivals that cost one day's pay</label>
                                <input type="number" id="late_days_per_deduction" name="late_days_per_deduction" value="{{ old('late_days_per_deduction', $s['late_days_per_deduction']) }}" min="0" max="30" class="form-control" required>
                                <div class="form-text">For example 3 means every 3 late arrivals in a month cost one day. Enter 0 to switch late deductions off.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save payroll rules</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ Holidays ============ --}}
    @if ($tab === 'holidays')
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">Holidays</div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Date</th><th>Name</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($holidays as $holiday)
                                    <tr>
                                        <td class="text-nowrap">{{ $holiday->date->format('D, d M Y') }}</td>
                                        <td>{{ $holiday->name }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('settings.holidays.destroy', $holiday) }}" class="d-inline" data-confirm="Remove {{ $holiday->name }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3"><div class="empty-state"><i class="bi bi-calendar-event"></i>No holidays have been added.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">Add a holiday</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('settings.holidays.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="holiday_date" class="form-label">Date</label>
                                <input type="date" id="holiday_date" name="date" value="{{ old('date') }}" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="holiday_name" class="form-label">Name</label>
                                <input type="text" id="holiday_name" name="name" value="{{ old('name') }}" maxlength="100" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Add holiday</button>
                            <div class="form-text mt-2">Add Eid and other religious holidays here each year, as their dates change with the moon.</div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ Tax ============ --}}
    @if ($tab === 'tax')
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ $s['tax_slabs_note'] ?: 'Check these rates against the rules your organization follows.' }}
            Income tax is calculated from annual slabs: monthly taxable income &times; 12 is taxed slice by slice, then divided by 12.
            Payroll uses the set with the latest start date that is not after the payroll month.
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                @forelse ($slabSets as $date => $slabs)
                    <div class="card mb-3">
                        <div class="card-header">Rates from {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th class="text-end">From (৳ per year)</th><th class="text-end">Up to (৳ per year)</th><th class="text-end">Rate</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($slabs as $slab)
                                        <tr>
                                            <td class="text-end">{{ number_format($slab->from_amount, 0) }}</td>
                                            <td class="text-end">{{ $slab->to_amount === null ? 'and above' : number_format($slab->to_amount, 0) }}</td>
                                            <td class="text-end">{{ $slab->rate_percent + 0 }}%</td>
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('settings.slabs.destroy', $slab) }}" class="d-inline" data-confirm="Remove this tax slab?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="card"><div class="card-body empty-state"><i class="bi bi-percent"></i>No tax slabs. Payroll will deduct no income tax.</div></div>
                @endforelse
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">Add a slab</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('settings.slabs.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="effective_from" class="form-label">Rates start on</label>
                                <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from', today()->toDateString()) }}" class="form-control" required>
                                <div class="form-text">Slabs with the same start date form one rate table.</div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label for="from_amount" class="form-label">From (৳ / year)</label>
                                    <input type="number" id="from_amount" name="from_amount" value="{{ old('from_amount') }}" min="0" step="1" class="form-control" required>
                                </div>
                                <div class="col-6">
                                    <label for="to_amount" class="form-label">Up to (৳ / year)</label>
                                    <input type="number" id="to_amount" name="to_amount" value="{{ old('to_amount') }}" min="0" step="1" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="rate_percent" class="form-label">Rate (%)</label>
                                <input type="number" id="rate_percent" name="rate_percent" value="{{ old('rate_percent') }}" min="0" max="100" step="0.01" class="form-control" required>
                                <div class="form-text">Leave "Up to" empty for the top slab ("and above").</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Add slab</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
