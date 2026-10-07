<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * Write one line to the audit trail. Rows are only ever inserted, never edited.
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        ?int $recordId = null,
        ?int $userId = null
    ): void {
        AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'description' => mb_substr($description, 0, 500),
            'ip_address' => request()->ip(),
        ]);
    }
}
