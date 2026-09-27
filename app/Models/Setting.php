<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /**
     * القيم الافتراضية لكل الإعدادات القابلة للتعديل.
     */
    public const DEFAULTS = [
        'demo_mode' => true,
        'ai_enabled' => false,
        // فرق السوق المحلي على سعر الذهب العالمي (نسبة)
        'gold_local_premium' => 0.01,
        // أجور الصياغة (دينار للغرام) حسب المنشأ
        'making_charges' => ['gulf' => 12000, 'turkish' => 9000, 'italian' => 15000, 'iraqi' => 7000],
        // خصم شراء الذهب المستعمل (الكسر) كنسبة من سعر الخام
        'scrap_discount' => 0.03,
        // سعر البنك المركزي الرسمي للدولار
        'cbi_official_rate' => 1310,
        // تكاليف تقريبية لحاسبة السيارات
        'car_customs_rate' => 0.15,
        'car_registration_iqd' => 1500000,
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('settings.all', 300, fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}
