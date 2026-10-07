<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public const STATUSES = ['present', 'absent', 'half_day', 'late', 'leave'];

    protected $fillable = ['employee_id', 'date', 'check_in', 'check_out', 'working_hours', 'status', 'remarks'];

    protected function casts(): array
    {
        return ['date' => 'date', 'working_hours' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
