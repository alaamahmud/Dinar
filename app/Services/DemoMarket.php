<?php

namespace App\Services;

use App\Models\City;
use App\Models\Instrument;
use App\Models\PriceReading;
use App\Models\Source;
use App\Services\Pricing\PriceService;
use Carbon\CarbonInterface;

/**
 * محاكي السوق للوضع التجريبي: يولّد قراءات واقعية حتى تبدو المنصة حية
 * قبل ربط المصادر الحقيقية. يُعطَّل تلقائياً بإيقاف «الوضع التجريبي» من لوحة الإدارة.
 */
class DemoMarket
{
    /** فرق كل مدينة عن بورصة الكفاح (دينار لكل 100 دولار) */
    public const CITY_OFFSETS = [
        'baghdad-kifah' => 0,
        'baghdad-harthiya' => 100,
        'baghdad-samawal' => 50,
        'erbil' => 250,
        'sulaymaniyah' => 350,
        'basra' => 400,
        'najaf' => 150,
        'karbala' => 200,
        'mosul' => 300,
        'duhok' => 400,
    ];

    public function __construct(private readonly PriceService $prices) {}

    public function tick(?CarbonInterface $at = null): void
    {
        $at ??= now();
        $sources = Source::where('type', 'manual')->where('name', 'like', 'محاكاة%')->get();
        if ($sources->isEmpty()) {
            return;
        }

        $usd = Instrument::byCode('usd');
        $last = $this->prices->latest('usd', null, null, $at)?->mid ?? 145000.0;
        // مشي عشوائي مع ميل للعودة نحو 145,000
        $mid = $last * (1 + $this->gauss() * 0.0006) + (145000 - $last) * 0.002;

        foreach (City::all() as $city) {
            $cityMid = $mid + (self::CITY_OFFSETS[$city->slug] ?? 0);
            foreach ($sources as $source) {
                $noisy = $cityMid * (1 + $this->gauss() * 0.0008);
                $this->reading($usd, $source, $city->id, $noisy, 250, $at);
            }
            if ($city->is_primary) {
                $this->reading($usd, $sources->first(), $city->id, $cityMid * 0.988, 300, $at, 'white');
                $this->reading($usd, $sources->first(), $city->id, $cityMid * 0.994, 300, $at, 'small');
            }
        }

        $ounce = $this->prices->latest('gold_ounce', null, null, $at)?->mid ?? 3900.0;
        $this->reading(Instrument::byCode('gold_ounce'), $sources->first(), null, $ounce * (1 + $this->gauss() * 0.0012), 0, $at);

        $silver = $this->prices->latest('silver_ounce', null, null, $at)?->mid ?? 46.0;
        $this->reading(Instrument::byCode('silver_ounce'), $sources->first(), null, $silver * (1 + $this->gauss() * 0.002), 0, $at);

        $this->reading(Instrument::byCode('usdt'), $sources->first(), null, $mid * 1.004, 400, $at);
    }

    private function reading(Instrument $instrument, Source $source, ?int $cityId, float $mid, float $spread, CarbonInterface $at, ?string $noteType = null): void
    {
        PriceReading::create([
            'instrument_id' => $instrument->id,
            'source_id' => $source->id,
            'city_id' => $cityId,
            'buy' => round($mid - $spread / 2, 2),
            'sell' => round($mid + $spread / 2, 2),
            'note_type' => $noteType,
            'recorded_at' => $at,
        ]);
    }

    /** عدد عشوائي بتوزيع طبيعي معياري */
    public function gauss(): float
    {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9);
        $v = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
