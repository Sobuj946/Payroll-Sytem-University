<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    public const CATEGORIES = ['teacher' => 'Teacher', 'officer' => 'Officer', 'staff' => 'Staff'];

    protected $fillable = ['name', 'category', 'description', 'status'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
