<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public static function primary(): ?self
    {
        return static::where('is_primary', true)->first() ?? static::orderBy('sort')->first();
    }
}
