<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends Model
{
    protected $fillable = ['code', 'name', 'type', 'calc_type', 'default_value', 'source', 'is_taxable', 'status'];

    protected function casts(): array
    {
        return ['default_value' => 'decimal:2', 'is_taxable' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
