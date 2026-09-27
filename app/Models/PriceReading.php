<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceReading extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'recorded_at' => 'datetime',
            'buy' => 'float',
            'sell' => 'float',
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

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mid(): float
    {
        $values = array_filter([$this->buy, $this->sell]);

        return $values ? array_sum($values) / count($values) : 0.0;
    }
}
