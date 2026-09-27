<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CbiAuction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sales_musd' => 'float',
            'cash_musd' => 'float',
            'transfers_musd' => 'float',
        ];
    }
}
