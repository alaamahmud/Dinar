<?php

namespace App\Services\Analytics;

use App\Models\Prediction;
use App\Services\Pricing\PriceService;
use Illuminate\Support\Carbon;

/**
 * يصحح توقعات المسابقة بعد إغلاق اليوم ويوزع النقاط.
 * النقاط: 100 للتوقع المطابق تقريباً، تنقص كلما زاد الخطأ.
 */
class PredictionScorer
{
    public function __construct(private readonly PriceService $prices) {}

    public static function pointsFor(float $errorPct): int
    {
        return (int) max(0, round(100 - $errorPct * 100));
    }

    public function score(Carbon $date): int
    {
        $scored = 0;

        Prediction::with(['user', 'instrument'])
            ->whereDate('target_date', $date)
            ->whereNull('actual')
            ->get()
            ->groupBy('instrument_id')
            ->each(function ($predictions) use ($date, &$scored) {
                $code = $predictions->first()->instrument->code;
                $close = $this->prices->dayStats($code, $date)['close'];
                if ($close === null) {
                    return;
                }

                foreach ($predictions as $prediction) {
                    $error = abs($prediction->predicted - $close) / $close * 100;
                    $points = self::pointsFor($error);
                    $prediction->update(['actual' => $close, 'error_pct' => round($error, 4), 'points' => $points]);
                    $prediction->user->increment('points', $points);
                    $scored++;
                }
            });

        return $scored;
    }
}
