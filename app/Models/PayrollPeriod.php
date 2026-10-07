<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    // Allowed workflow order. PayrollService will enforce the transitions.
    public const STATUSES = ['draft', 'processing', 'processed', 'reviewed', 'approved', 'paid', 'closed'];

    protected $fillable = [
        'month', 'year', 'start_date', 'end_date', 'status',
        'created_by', 'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'approved_at' => 'datetime'];
    }

    public function getLabelAttribute(): string
    {
        return date('F', mktime(0, 0, 0, $this->month, 1)) . ' ' . $this->year;
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['approved', 'paid', 'closed'], true);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
