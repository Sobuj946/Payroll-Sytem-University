<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'allocated', 'used'];

    protected function casts(): array
    {
        return ['allocated' => 'decimal:1', 'used' => 'decimal:1'];
    }

    public function getRemainingAttribute(): float
    {
        return (float) $this->allocated - (float) $this->used;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
