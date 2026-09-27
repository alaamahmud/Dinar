<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSnapshot extends Model
{
    protected $guarded = [];

    public const CONFIDENCE = [
        'high' => 'ثقة عالية',
        'medium' => 'ثقة متوسطة',
        'low' => 'غير مؤكد',
    ];

    protected function casts(): array
    {
        return [
            'computed_at' => 'datetime',
            'buy' => 'float',
            'sell' => 'float',
            'mid' => 'float',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function confidenceLabel(): string
    {
        return self::CONFIDENCE[$this->confidence] ?? $this->confidence;
    }
}
