<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['has_cities' => 'boolean'];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(PriceReading::class);
    }

    /** @var array<string, self> */
    protected static array $codeCache = [];

    public static function byCode(string $code): self
    {
        return static::$codeCache[$code] ??= static::where('code', $code)->firstOrFail();
    }

    public static function flushCodeCache(): void
    {
        static::$codeCache = [];
    }
}
