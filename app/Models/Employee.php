<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

    public const EMPLOYMENT_TYPES = [
        'permanent' => 'Permanent',
        'contract' => 'Contract',
        'probation' => 'Probation',
        'part_time' => 'Part-time',
        'visiting' => 'Visiting / guest teacher',
        'master_roll' => 'Master roll / daily wage',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'on_leave' => 'On Leave',
        'suspended' => 'Suspended',
        'resigned' => 'Resigned',
        'terminated' => 'Terminated',
    ];

    protected $fillable = [
        'employee_code', 'first_name', 'last_name', 'email', 'phone', 'address', 'gender',
        'date_of_birth', 'nid', 'tin', 'emergency_contact_name', 'emergency_contact_phone', 'photo',
        'department_id', 'designation_id', 'pay_grade_id', 'employment_type', 'joining_date', 'basic_salary', 'last_increment_on',
        'bank_name', 'bank_account_number', 'bank_branch', 'pf_account_no', 'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'last_increment_on' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    /** Next free code in the PREFIX-0001 format. The prefix is a setting (EMP by default, HSTU for the university). */
    public static function nextCode(): string
    {
        $prefix = Setting::get('employee_code_prefix') ?: 'EMP';
        $last = static::withTrashed()->orderByDesc('id')->value('employee_code');
        $number = $last ? ((int) substr($last, (int) strrpos($last, '-') + 1)) + 1 : 1;

        return sprintf('%s-%04d', $prefix, $number);
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(mb_substr($this->first_name, 0, 1) . mb_substr($this->last_name, 0, 1));
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('uploads/employees/' . $this->photo) : null;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function payGrade(): BelongsTo
    {
        return $this->belongsTo(PayGrade::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
