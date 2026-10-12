@extends('layouts.app')

@section('title', 'Backup & Export')

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="bi bi-database-down me-2"></i>Full database backup</div>
                <div class="card-body">
                    <p>Downloads one <code>.sql</code> file with every table of the <strong>{{ $database }}</strong> database: employees, attendance, leave, salary, payroll, payments, users and the audit log.</p>

                    <form method="POST" action="{{ route('backup.download') }}" data-confirm="Download a full backup now?">
                        @csrf
                        <button type="submit" class="btn btn-primary"><i class="bi bi-download me-1"></i>Download backup</button>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">How to restore a backup</div>
                <div class="card-body">
                    <p class="mb-2">On the computer that runs the system, in a terminal:</p>
                    <pre class="bg-body-tertiary border rounded p-3 mb-2"><code>mysql -u pms -p {{ $database }} &lt; payroll-backup-YYYYMMDD-HHMMSS.sql</code></pre>
                    <p class="small text-muted mb-0">Restoring replaces the current data with the contents of the file. Try it on a spare database first.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="alert alert-warning">
                <div class="fw-semibold mb-1"><i class="bi bi-shield-exclamation me-1"></i>Keep backups safe</div>
                A backup contains salaries, bank details, NID numbers and password hashes. Store it somewhere private, never email it, and delete old copies you no longer need.
            </div>
            <div class="card">
                <div class="card-header">Good to know</div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item">Employee photos and the company logo are files, not database rows. Copy the folder <code>public/uploads</code> to keep them too.</li>
                    <li class="list-group-item">Temporary data (sign-in sessions, cache) is left out.</li>
                    <li class="list-group-item">Each download is written to the audit log.</li>
                    <li class="list-group-item">For tables of a single area, use the CSV buttons on the <a href="{{ route('reports.index') }}">Reports</a> pages.</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
