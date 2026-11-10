<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayGrade extends Model
{
    protected $fillable = ['grade_no', 'name', 'min_basic', 'max_basic', 'increment_percent', 'status'];

    protected function casts(): array
    {
        return [
            'min_basic' => 'decimal:2',
            'max_basic' => 'decimal:2',
            'increment_percent' => 'decimal:2',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** The highest basic salary of the grade. A fixed scale has no separate maximum. */
    public function getCeilingAttribute(): float
    {
        return (float) ($this->max_basic ?? $this->min_basic);
    }

    public function allows(float $basic): bool
    {
        return $basic >= (float) $this->min_basic && $basic <= $this->ceiling;
    }

    /** "Grade 9 (৳22,000 - ৳53,060)" */
    public function getLabelAttribute(): string
    {
        $range = $this->max_basic === null
            ? '৳' . number_format((float) $this->min_basic, 0)
            : '৳' . number_format((float) $this->min_basic, 0) . ' - ৳' . number_format($this->ceiling, 0);

        return "Grade {$this->grade_no} ({$range})";
    }
}
