@extends('layouts.app')

@section('title', 'Salary Slip · ' . $slip['employee']->full_name)

@push('styles')
    @include('payslips._styles')
    <style>
        .slip-paper { max-width: 820px; margin: 0 auto; border: 1px solid var(--bs-border-color); box-shadow: 0 1px 4px rgba(0, 0, 0, .08); }
        @media print {
            .app-sidebar, .app-header, .app-footer, .no-print, .toast-container { display: none !important; }
            .app-shell, .app-main { display: block !important; }
            .app-content { padding: 0 !important; }
            body { background: #fff !important; }
            .slip-paper { border: 0; box-shadow: none; max-width: none; }
            @page { size: A4; margin: 12mm; }
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="{{ $backUrl }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
            @if ($canPdf)
                <a href="{{ $pdfUrl }}" class="btn btn-primary"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
            @endif
        </div>
    </div>

    <div class="slip-paper">
        @include('payslips._slip')
    </div>
@endsection
