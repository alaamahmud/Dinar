<?php

namespace App\Services\Pricing\Sources;

use App\Models\Instrument;
use App\Models\PriceReading;
use App\Models\Setting;
use App\Models\Source;

/**
 * سعر البنك المركزي الرسمي — يتغير نادراً، لذا يؤخذ من الإعدادات
 * (يحدّثه المدير من لوحة الإدارة عند صدور قرار جديد).
 */
class CbiSource implements PriceSource
{
    public function fetch(Source $source): int
    {
        $rate = (float) Setting::get('cbi_official_rate');

        PriceReading::create([
            'instrument_id' => Instrument::byCode('usd_cbi')->id,
            'source_id' => $source->id,
            'buy' => $rate * 100,
            'sell' => $rate * 100,
            'recorded_at' => now(),
        ]);

        return 1;
    }
}
