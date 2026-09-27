<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Pricing\GoldCalculator;
use App\Services\Pricing\PriceService;
use Illuminate\Support\Carbon;

/**
 * الحاسبات المالية. كل الأسعار بالدينار، وسعر الدولار = سعر 100 دولار.
 */
class Calculators
{
    public const ASSETS = [
        'usd' => 'الدولار',
        'gold21' => 'ذهب عيار 21',
        'gold24' => 'ذهب عيار 24',
    ];

    public const DESTINATIONS = [
        'iran' => ['name' => 'إيران', 'currency' => 'irr', 'daily_usd' => 45],
        'turkey' => ['name' => 'تركيا', 'currency' => 'try', 'daily_usd' => 110],
        'saudi' => ['name' => 'السعودية (عمرة)', 'currency' => 'sar', 'daily_usd' => 120],
        'jordan' => ['name' => 'الأردن', 'currency' => 'jod', 'daily_usd' => 90],
    ];

    public function __construct(
        private readonly PriceService $prices,
        private readonly GoldCalculator $gold,
    ) {}

    /**
     * «لو اشتريت»: قيمة مبلغ بالدينار لو حُوّل لأصل في تاريخ سابق.
     *
     * @return array{units: float, then_price: float, now_price: float, value_now: float, profit: float, profit_pct: float}|null
     */
    public function whatIf(float $amountIqd, string $asset, Carbon $date): ?array
    {
        $then = $this->prices->priceAt($asset, $date->copy()->endOfDay());
        $now = $this->prices->latest($asset)?->mid;
        if (! $then || ! $now) {
            return null;
        }

        $unitSize = $asset === 'usd' ? 100 : 1; // الدولار مسعّر لكل 100
        $units = $amountIqd / $then * $unitSize;
        $valueNow = $amountIqd / $then * $now;

        return [
            'units' => round($units, $asset === 'usd' ? 0 : 2),
            'then_price' => $then,
            'now_price' => $now,
            'value_now' => round($valueNow),
            'profit' => round($valueNow - $amountIqd),
            'profit_pct' => round(($valueNow - $amountIqd) / $amountIqd * 100, 1),
        ];
    }

    /**
     * «راتبك الحقيقي»: قيمة الراتب بالدولار والذهب عبر الأشهر.
     *
     * @return array{rows: list<array{month: string, usd: float, gold_grams: float}>, loss_pct: ?float}
     */
    public function salaryValue(float $salaryIqd, int $months = 12): array
    {
        $rows = [];
        for ($i = $months; $i >= 0; $i--) {
            $at = now()->subMonths($i)->endOfMonth()->min(now());
            $usd = $this->prices->priceAt('usd', $at);
            $gold = $this->prices->priceAt('gold21', $at);
            if (! $usd) {
                continue;
            }
            $rows[] = [
                'month' => $at->translatedFormat('M Y'),
                'usd' => round($salaryIqd / $usd * 100),
                'gold_grams' => $gold ? round($salaryIqd / ($gold / 5), 2) : 0,
            ];
        }

        $loss = count($rows) >= 2 && $rows[0]['usd'] > 0
            ? round((end($rows)['usd'] - $rows[0]['usd']) / $rows[0]['usd'] * 100, 1)
            : null;

        return ['rows' => $rows, 'loss_pct' => $loss];
    }

    /**
     * كلفة استيراد سيارة (تقريبية — النسب قابلة للتعديل من الإدارة).
     *
     * @return array<string, float>
     */
    public function carImport(float $priceUsd, float $shippingUsd, float $usdPer100): array
    {
        $rate = $usdPer100 / 100;
        $carIqd = $priceUsd * $rate;
        $shippingIqd = $shippingUsd * $rate;
        $customs = ($carIqd + $shippingIqd) * (float) Setting::get('car_customs_rate');
        $registration = (float) Setting::get('car_registration_iqd');

        return [
            'car' => round($carIqd),
            'shipping' => round($shippingIqd),
            'customs' => round($customs),
            'registration' => round($registration),
            'total' => round($carIqd + $shippingIqd + $customs + $registration),
            'total_usd' => round(($carIqd + $shippingIqd + $customs + $registration) / $rate),
        ];
    }

    /**
     * مقارنة طرق استلام حوالة من الخارج.
     *
     * @return list<array{name: string, fee_usd: float, rate: float, received: float, note: string}>
     */
    public function remittance(float $amountUsd, float $parallelPer100, ?float $officialPer100, ?float $usdtPer100): array
    {
        $methods = [
            [
                'name' => 'تحويل مصرفي / ويسترن يونيون (استلام بالدينار)',
                'fee_usd' => max(5, $amountUsd * 0.03),
                'rate' => ($officialPer100 ?? $parallelPer100 * 0.93) / 100,
                'note' => 'رسوم أعلى وغالباً يُصرف بسعر قريب من الرسمي',
            ],
            [
                'name' => 'تحويل مصرفي (استلام بالدولار ثم البيع في السوق)',
                'fee_usd' => max(5, $amountUsd * 0.03),
                'rate' => $parallelPer100 / 100,
                'note' => 'تستلم دولاراً وتبيعه بسعر السوق',
            ],
            [
                'name' => 'حوالة تقليدية (مكتب حوالات)',
                'fee_usd' => $amountUsd * 0.01,
                'rate' => $parallelPer100 / 100 * 0.997,
                'note' => 'سريعة، والعمولة حسب المكتب',
            ],
        ];

        if ($usdtPer100) {
            $methods[] = [
                'name' => 'USDT عبر منصة P2P',
                'fee_usd' => 1,
                'rate' => $usdtPer100 / 100 * 0.995,
                'note' => 'رسوم شبكة منخفضة، مع مخاطر التعامل مع أفراد',
            ];
        }

        return collect($methods)->map(fn ($m) => $m + [
            'received' => round(max(0, $amountUsd - $m['fee_usd']) * $m['rate']),
        ])->sortByDesc('received')->values()->all();
    }

    /**
     * ميزانية السفر بالعملة المحلية وبالدينار.
     *
     * @return array{destination: array<string, mixed>, total_usd: float, total_iqd: float, local_amount: ?float, local_label: string}
     */
    public function travel(string $destination, int $days, int $people, float $usdPer100, ?float $dailyUsd = null): array
    {
        $dest = self::DESTINATIONS[$destination] ?? self::DESTINATIONS['iran'];
        $dailyUsd ??= $dest['daily_usd'];
        $totalUsd = $dailyUsd * $days * $people;
        $totalIqd = $totalUsd * $usdPer100 / 100;

        $local = $this->prices->latest($dest['currency'])?->mid;
        $localAmount = null;
        $label = '';
        if ($local) {
            if ($dest['currency'] === 'irr') {
                $localAmount = $totalIqd / 1000 * $local; // التومان مسعّر لكل 1000 دينار
                $label = 'تومان';
            } else {
                $localAmount = $totalIqd / $local;
                $label = ['try' => 'ليرة تركية', 'sar' => 'ريال سعودي', 'jod' => 'دينار أردني'][$dest['currency']];
            }
        }

        return [
            'destination' => $dest,
            'total_usd' => $totalUsd,
            'total_iqd' => round($totalIqd),
            'local_amount' => $localAmount ? round($localAmount) : null,
            'local_label' => $label,
        ];
    }

    /**
     * حاسبة التاجر: ربح صفقة شراء/بيع كبيرة.
     *
     * @return array<string, float>
     */
    public function trade(float $amountUsd, float $buyPer100, float $sellPer100, float $costsIqd = 0): array
    {
        $cost = $amountUsd / 100 * $buyPer100;
        $revenue = $amountUsd / 100 * $sellPer100;
        $profit = $revenue - $cost - $costsIqd;

        return [
            'cost' => round($cost),
            'revenue' => round($revenue),
            'profit' => round($profit),
            'margin_pct' => $cost > 0 ? round($profit / $cost * 100, 3) : 0,
            'break_even' => $amountUsd > 0 ? round($buyPer100 + $costsIqd / $amountUsd * 100) : 0,
        ];
    }

    /**
     * @return array{nisab_gold: float, nisab_silver: ?float, wealth: float, due: bool, zakat: float}
     */
    public function zakat(float $cashIqd, float $usdAmount, float $goldGrams21, float $usdPer100, ?float $ounceUsd, ?float $silverGram): array
    {
        $gold21Gram = ($this->prices->latest('gold21')?->mid ?? 0) / 5;
        $wealth = $cashIqd + $usdAmount * $usdPer100 / 100 + $goldGrams21 * $gold21Gram;
        $nisab = $ounceUsd ? $this->gold->nisab($ounceUsd, $usdPer100, $silverGram) : ['gold' => 0, 'silver' => null];

        return [
            'nisab_gold' => $nisab['gold'],
            'nisab_silver' => $nisab['silver'],
            'wealth' => round($wealth),
            'due' => $nisab['gold'] > 0 && $wealth >= $nisab['gold'],
            'zakat' => round($wealth * 0.025),
        ];
    }
}
