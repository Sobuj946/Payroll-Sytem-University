<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // Insert-only table: there is no updated_at column.
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'module', 'record_id', 'description', 'ip_address'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
