<?php

namespace App\Services\Analytics;

use App\Services\Pricing\PriceService;
use Carbon\CarbonInterface;

/**
 * كاشف الحركات غير الطبيعية: يقارن تغيّر آخر ساعة بالتذبذب المعتاد.
 */
class AnomalyDetector
{
    public function __construct(private readonly PriceService $prices) {}

    /**
     * @return array{change_pct: float, z: float, direction: string, message: string}|null
     */
    public function detect(string $code = 'usd', ?CarbonInterface $at = null, float $zThreshold = 3.0, float $minPct = 0.4): ?array
    {
        $at ??= now();
        $series = array_column($this->prices->series($code, '7d', null, $at), 'v');
        if (count($series) < 24) {
            return null;
        }

        $returns = Stats::returns($series);
        $last = array_pop($returns);
        $std = Stats::std($returns);
        $mean = Stats::mean($returns);
        if ($std <= 0) {
            return null;
        }

        $z = ($last - $mean) / $std;
        $pct = $last * 100;

        if (abs($z) < $zThreshold || abs($pct) < $minPct) {
            return null;
        }

        $direction = $pct > 0 ? 'up' : 'down';

        return [
            'change_pct' => round($pct, 2),
            'z' => round($z, 1),
            'direction' => $direction,
            'message' => sprintf(
                '%s مفاجئ في سعر الدولار: %s%% خلال ساعة — أكبر بـ %s مرات من الحركة المعتادة',
                $direction === 'up' ? 'ارتفاع' : 'انخفاض',
                number_format(abs($pct), 2),
                number_format(abs($z), 1),
            ),
        ];
    }
}
