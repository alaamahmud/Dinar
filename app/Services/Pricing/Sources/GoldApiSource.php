<?php

namespace App\Services\Pricing\Sources;

use App\Models\Instrument;
use App\Models\PriceReading;
use App\Models\Source;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * سعر أونصة الذهب (والفضة اختيارياً) بالدولار من واجهة برمجية عالمية مثل GoldAPI.
 * الإعدادات: config.metal = XAU أو XAG
 */
class GoldApiSource implements PriceSource
{
    public function fetch(Source $source): int
    {
        $key = config('dinar.gold_api.key');
        if (! $key) {
            throw new RuntimeException('مفتاح GOLD_API_KEY غير مضبوط في ملف .env');
        }

        $metal = $source->config['metal'] ?? 'XAU';
        $url = str_replace('XAU', $metal, (string) config('dinar.gold_api.url'));

        $response = Http::timeout(15)->withHeaders(['x-access-token' => $key])->get($url);
        $response->throw();

        $price = (float) ($response->json('price') ?? 0);
        if ($price <= 0) {
            throw new RuntimeException('استجابة غير متوقعة من واجهة الذهب');
        }

        PriceReading::create([
            'instrument_id' => Instrument::byCode($metal === 'XAG' ? 'silver_ounce' : 'gold_ounce')->id,
            'source_id' => $source->id,
            'buy' => $price,
            'sell' => $price,
            'recorded_at' => now(),
        ]);

        return 1;
    }
}
