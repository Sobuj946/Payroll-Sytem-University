<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\BackupService;

class BackupController extends Controller
{
    public function __construct(private BackupService $backup)
    {
    }

    public function index()
    {
        return view('backup.index', ['database' => config('database.connections.' . config('database.default') . '.database')]);
    }

    /** POST, so that it only runs when the Admin presses the button. */
    public function download()
    {
        AuditService::log('backup', 'backup', 'A full database backup was downloaded');

        return response()->streamDownload(function () {
            set_time_limit(0);
            $out = fopen('php://output', 'w');
            $this->backup->write($out);
            fclose($out);
        }, $this->backup->fileName(), ['Content-Type' => 'application/sql; charset=UTF-8']);
    }
}
