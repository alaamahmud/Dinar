<?php

namespace App\Services\Pricing;

use App\Models\Setting;

class GoldCalculator
{
    public const KARATS = [24, 22, 21, 18];

    public const ORIGINS = [
        'gulf' => 'خليجي',
        'turkish' => 'تركي',
        'italian' => 'إيطالي',
        'iraqi' => 'عراقي',
    ];

    /**
     * سعر غرام الذهب بالدينار.
     *
     * @param  float  $ounceUsd  سعر الأونصة بالدولار
     * @param  float  $usdPer100  سعر 100 دولار بالدينار
     */
    public function gramPrice(int $karat, float $ounceUsd, float $usdPer100, ?float $premium = null): float
    {
        $premium ??= (float) Setting::get('gold_local_premium');
        $grams = (float) config('dinar.grams_per_ounce', 31.1035);

        return $ounceUsd / $grams * ($karat / 24) * ($usdPer100 / 100) * (1 + $premium);
    }

    public function mithqalPrice(int $karat, float $ounceUsd, float $usdPer100, ?float $premium = null): float
    {
        return $this->gramPrice($karat, $ounceUsd, $usdPer100, $premium) * (float) config('dinar.mithqal_grams', 5);
    }

    /**
     * «هل أنصفك الصائغ؟» — يقارن السعر المعروض بالسعر العادل.
     *
     * @return array{raw: float, making: float, fair: float, offered: float, diff_pct: float, verdict: string, tone: string, scrap: float}
     */
    public function fairPrice(float $grams, int $karat, string $origin, float $offered, float $ounceUsd, float $usdPer100, string $side = 'buy'): array
    {
        $raw = $this->gramPrice($karat, $ounceUsd, $usdPer100) * $grams;
        $makingPerGram = (float) (Setting::get('making_charges')[$origin] ?? 10000);
        $making = $makingPerGram * $grams;
        $scrap = $raw * (1 - (float) Setting::get('scrap_discount'));

        if ($side === 'sell') {
            // المستخدم يبيع ذهبه للصائغ: السعر العادل قريب من سعر الكسر
            $fair = $scrap;
            $diff = $fair > 0 ? ($offered - $fair) / $fair * 100 : 0;

            [$verdict, $tone] = match (true) {
                $diff >= -2 => ['عرض عادل ✅', 'good'],
                $diff >= -6 => ['أقل قليلاً من المتوقع ⚠️', 'warn'],
                default => ['العرض منخفض — جرّب محلاً آخر ❌', 'bad'],
            };
        } else {
            $fair = $raw + $making;
            $diff = $fair > 0 ? ($offered - $fair) / $fair * 100 : 0;

            [$verdict, $tone] = match (true) {
                $offered < $raw => ['السعر أقل من قيمة الذهب الخام — تأكد من العيار والوزن 🔍', 'warn'],
                $diff <= 3 => ['سعر عادل ✅', 'good'],
                $diff <= 8 => ['أعلى قليلاً من المتوقع ⚠️', 'warn'],
                default => ['السعر مرتفع — فاوض أو جرّب محلاً آخر ❌', 'bad'],
            };
        }

        return [
            'raw' => round($raw),
            'making' => round($making),
            'fair' => round($fair),
            'offered' => round($offered),
            'diff_pct' => round($diff, 1),
            'verdict' => $verdict,
            'tone' => $tone,
            'scrap' => round($scrap),
        ];
    }

    /**
     * نصاب الزكاة بالدينار (85 غرام ذهب عيار 24، أو 595 غرام فضة).
     *
     * @return array{gold: float, silver: ?float}
     */
    public function nisab(float $ounceUsd, float $usdPer100, ?float $silverGramIqd = null): array
    {
        return [
            'gold' => round($this->gramPrice(24, $ounceUsd, $usdPer100, 0) * 85),
            'silver' => $silverGramIqd ? round($silverGramIqd * 595) : null,
        ];
    }
}
