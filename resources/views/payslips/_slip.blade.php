@php
    $p = $slip['payroll'];
    $e = $slip['employee'];
    $period = $slip['period'];
    $co = $slip['company'];
    $payment = $slip['payment'];
    $m = fn ($amount) => $cur . number_format((float) $amount, 2);
@endphp

<div class="slip">
    <table class="head">
        <tr>
            <td>
                <table>
                    <tr>
                        @if ($co['logo'])
                            <td style="width: 1%;"><img src="{{ $co['logo'] }}" class="logo" alt=""></td>
                        @endif
                        <td>
                            <div class="company">{{ $co['name'] }}</div>
                            @if ($co['address']) <div class="muted">{{ $co['address'] }}</div> @endif
                            <div class="muted">
                                @if ($co['phone']) Phone: {{ $co['phone'] }} @endif
                                @if ($co['phone'] && $co['email']) &nbsp;|&nbsp; @endif
                                @if ($co['email']) Email: {{ $co['email'] }} @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="title">
                SALARY SLIP
                <div class="muted" style="font-size: 12px; font-weight: normal; letter-spacing: 0;">{{ $period->start_date->format('F Y') }}</div>
                <div class="muted" style="font-size: 10.5px; font-weight: normal; letter-spacing: 0;">Slip no. {{ $slip['slipNo'] }}</div>
            </td>
        </tr>
    </table>

    <table class="info section">
        <tr>
            <td class="label">Employee name</td><td><strong>{{ $e->full_name }}</strong></td>
            <td class="label">Employee ID</td><td>{{ $e->employee_code }}</td>
        </tr>
        <tr>
            <td class="label">Department</td><td>{{ $e->department?->name }}</td>
            <td class="label">Designation</td><td>{{ $e->designation?->name }}</td>
        </tr>
        <tr>
            <td class="label">Date of joining</td><td>{{ $e->joining_date->format('d M Y') }}</td>
            <td class="label">Pay period</td><td>{{ $period->start_date->format('d M') }} &ndash; {{ $period->end_date->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="label">Bank</td><td>{{ $e->bank_name ?: '-' }}@if ($slip['account']) ({{ $slip['account'] }}) @endif</td>
            <td class="label">Basic salary</td><td>{{ $m($p->basic_salary) }}</td>
        </tr>
    </table>

    <table class="box section">
        <thead>
            <tr><th style="width: 30%;">Earnings</th><th class="num" style="width: 20%;">Amount</th><th style="width: 30%;">Deductions</th><th class="num" style="width: 20%;">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($slip['rows'] as [$earning, $deduction])
                <tr>
                    <td>{{ $earning?->name }}</td>
                    <td class="num">{{ $earning ? $m($earning->amount) : '' }}</td>
                    <td>{{ $deduction?->name }}</td>
                    <td class="num">{{ $deduction ? $m($deduction->amount) : '' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Gross salary</td><td class="num">{{ $m($p->gross_salary) }}</td>
                <td>Total deductions</td><td class="num">{{ $m($p->total_deductions) }}</td>
            </tr>
            <tr class="net">
                <td colspan="3">NET SALARY PAYABLE</td>
                <td class="num">{{ $m($p->net_salary) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="box section">
        <thead>
            <tr><th>Working days</th><th>Present</th><th>Absent</th><th>Unpaid leave</th><th>Late arrivals</th><th>Income tax</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $p->working_days }}</td>
                <td>{{ $p->present_days + 0 }}</td>
                <td>{{ $p->absent_days + 0 }}</td>
                <td>{{ $p->unpaid_leave_days + 0 }}</td>
                <td>{{ $p->late_count }}</td>
                <td>{{ $m($p->tax) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="info section">
        <tr>
            <td class="label">Payment status</td>
            <td><strong>{{ $payment ? ucfirst($payment->status) : 'Not paid yet' }}</strong></td>
            <td class="label">Payment date</td>
            <td>{{ $payment?->payment_date?->format('d M Y') ?? '-' }}</td>
        </tr>
        @if ($payment && $payment->status === 'paid')
            <tr>
                <td class="label">Method</td>
                <td>{{ \App\Services\PaymentService::METHODS[$payment->method] ?? $payment->method }}</td>
                <td class="label">Reference</td>
                <td>{{ $payment->transaction_reference ?: '-' }}</td>
            </tr>
        @endif
    </table>

    <div class="foot">
        This is a computer-generated salary slip and does not need a signature.
        Generated on {{ $slip['generatedAt']->format('d M Y, h:i A') }}.
    </div>
</div>
