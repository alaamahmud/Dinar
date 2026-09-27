<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $guarded = [];

    public const TYPES = [
        'telegram' => 'قناة تيليجرام عامة',
        'gold_api' => 'سعر الذهب العالمي (API)',
        'p2p' => 'مؤشر USDT في منصات P2P',
        'cbi' => 'البنك المركزي العراقي',
        'crowd' => 'بلاغات المستخدمين',
        'manual' => 'إدخال يدوي (الإدارة)',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'enabled' => 'boolean',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function readings(): HasMany
    {
        return $this->hasMany(PriceReading::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public static function ofType(string $type): ?self
    {
        return static::where('type', $type)->first();
    }
}
