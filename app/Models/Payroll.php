<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $fillable = [
        'payroll_period_id', 'employee_id', 'basic_salary', 'total_allowances', 'overtime', 'bonus',
        'gross_salary', 'tax', 'total_deductions', 'net_salary', 'working_days', 'present_days',
        'absent_days', 'unpaid_leave_days', 'late_count',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2', 'total_allowances' => 'decimal:2', 'overtime' => 'decimal:2',
            'bonus' => 'decimal:2', 'gross_salary' => 'decimal:2', 'tax' => 'decimal:2',
            'total_deductions' => 'decimal:2', 'net_salary' => 'decimal:2',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
