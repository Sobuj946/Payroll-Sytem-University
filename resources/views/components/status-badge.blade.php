@props(['status'])
@php
    $tones = [
        'active' => 'success', 'inactive' => 'secondary', 'on_leave' => 'info', 'suspended' => 'warning',
        'resigned' => 'secondary', 'terminated' => 'danger',
        'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary',
        'present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'half_day' => 'info', 'leave' => 'primary',
        'draft' => 'secondary', 'processing' => 'info', 'processed' => 'primary', 'reviewed' => 'primary',
        'paid' => 'success', 'closed' => 'dark', 'failed' => 'danger',
    ];
@endphp
<span class="badge text-bg-{{ $tones[$status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $status)) }}</span>
