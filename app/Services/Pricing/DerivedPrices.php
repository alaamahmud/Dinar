<?php

namespace App\Services\Pricing;

use App\Models\Instrument;
use App\Models\PriceSnapshot;
use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * أسعار تُحسب من غيرها: الذهب بالدينار، الفضة، الريال والدينار الأردني (مربوطان بالدولار)،
 * والليرة التركية والتومان (من أسعار عالمية تقريبية في الإعدادات).
 */
class DerivedPrices
{
    public function __construct(
        private readonly PriceService $prices,
        private readonly GoldCalculator $gold,
    ) {}

    public function compute(?CarbonInterface $at = null): void
    {
        $at ??= now();
        $usd = $this->prices->latest('usd', null, null, $at);
        if ($usd === null) {
            return;
        }

        $ounce = $this->prices->latest('gold_ounce', null, null, $at);
        $spread = max(0.001, ($usd->sell - $usd->buy) / max(1, $usd->mid));

        if ($ounce !== null) {
            foreach ([24 => 'gold24', 21 => 'gold21', 18 => 'gold18'] as $karat => $code) {
                $price = $this->gold->mithqalPrice($karat, $ounce->mid, $usd->mid);
                $this->store($code, $price, 0.01, $at, $this->worst($usd->confidence, $ounce->confidence));
            }
        }

        $silver = $this->prices->latest('silver_ounce', null, null, $at);
        if ($silver !== null) {
            $gram = $silver->mid / (float) config('dinar.grams_per_ounce') * ($usd->mid / 100);
            $this->store('silver', $gram, 0.02, $at, $this->worst($usd->confidence, $silver->confidence));
        }

        $usdPerOne = $usd->mid / 100;
        $cross = Setting::get('fx_cross', ['sar' => 3.75, 'jod' => 0.709, 'try' => 41.5, 'irr_toman' => 100000]);

        // الريال السعودي والدينار الأردني مربوطان بالدولار
        $this->store('sar', $usdPerOne / $cross['sar'], $spread, $at, $usd->confidence, onlyIfNoDirect: true);
        $this->store('jod', $usdPerOne / $cross['jod'], $spread, $at, $usd->confidence, onlyIfNoDirect: true);
        $this->store('try', $usdPerOne / $cross['try'], $spread * 2, $at, 'low', onlyIfNoDirect: true);
        // التومان: كم تومان مقابل 1000 دينار
        $this->store('irr', 1000 / $usdPerOne * $cross['irr_toman'], $spread * 2, $at, 'low', onlyIfNoDirect: true);
    }

    private function store(string $code, float $mid, float $spread, CarbonInterface $at, string $confidence, bool $onlyIfNoDirect = false): void
    {
        $instrument = Instrument::byCode($code);

        if ($onlyIfNoDirect && $instrument->readings()->where('recorded_at', '>=', $at->copy()->subHour())->exists()) {
            return; // توجد قراءات مباشرة من السوق — المجمّع يتكفل بها
        }

        PriceSnapshot::create([
            'instrument_id' => $instrument->id,
            'buy' => round($mid * (1 - $spread / 2), 2),
            'sell' => round($mid * (1 + $spread / 2), 2),
            'mid' => round($mid, 2),
            'confidence' => $confidence,
            'sample_count' => 1,
            'computed_at' => $at,
        ]);
    }

    private function worst(string ...$levels): string
    {
        foreach (['low', 'medium', 'high'] as $level) {
            if (in_array($level, $levels, true)) {
                return $level;
            }
        }

        return 'medium';
    }
}
