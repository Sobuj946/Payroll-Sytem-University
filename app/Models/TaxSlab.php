<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxSlab extends Model
{
    protected $fillable = ['from_amount', 'to_amount', 'rate_percent', 'effective_from'];

    protected function casts(): array
    {
        return [
            'from_amount' => 'decimal:2',
            'to_amount' => 'decimal:2',
            'rate_percent' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }
}
