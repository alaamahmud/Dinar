<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $guarded = [];

    public const CONDITIONS = [
        'above' => 'عندما يرتفع فوق',
        'below' => 'عندما ينخفض تحت',
        'spike' => 'عند حركة مفاجئة غير طبيعية',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'threshold' => 'float',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
