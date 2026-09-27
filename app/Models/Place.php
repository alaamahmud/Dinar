<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    protected $guarded = [];

    public const TYPES = [
        'exchange' => 'صيرفة',
        'gold' => 'محل ذهب',
    ];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
            'featured_until' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PlaceReview::class);
    }

    public function isFeatured(): bool
    {
        return $this->featured_until !== null && $this->featured_until->isFuture();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approved', true);
    }

    public function refreshRating(): void
    {
        $this->rating_avg = round((float) $this->reviews()->avg('rating'), 2);
        $this->rating_count = $this->reviews()->count();
        $this->save();
    }
}
