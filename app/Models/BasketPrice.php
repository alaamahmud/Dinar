<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasketPrice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(BasketItem::class, 'basket_item_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
