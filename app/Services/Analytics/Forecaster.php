<?php

namespace App\Services\Analytics;

use App\Services\Pricing\PriceService;
use Carbon\CarbonInterface;

/**
 * توقع اتجاه السعر بطرق إحصائية شفافة (بلا ذكاء اصطناعي):
 * الانحدار الخطي + الزخم + تقاطع المتوسطات المتحركة.
 * الاحتمالات محصورة بين 25% و75% عمداً — لا أحد يعرف المستقبل بيقين.
 */
class Forecaster
{
    public function __construct(private readonly PriceService $prices) {}

    /**
     * @return array{prob_up: int, direction: string, expected: float, low: float, high: float, ma7: float, ma21: float, signal: string, accuracy: ?int, tested: int}|null
     */
    public function forecast(string $code = 'usd', ?CarbonInterface $until = null): ?array
    {
        $closes = $this->prices->dailyCloses($code, 120, null, $until)->values()->all();
        if (count($closes) < 25) {
            return null;
        }

        $result = $this->predictFrom($closes);
        [$accuracy, $tested] = $this->backtest($closes);

        return [...$result, 'accuracy' => $accuracy, 'tested' => $tested];
    }

    /**
     * @param  list<float>  $closes
     * @return array{prob_up: int, direction: string, expected: float, low: float, high: float, ma7: float, ma21: float, signal: string}
     */
    public function predictFrom(array $closes): array
    {
        $last = end($closes);
        $recent = array_slice($closes, -14);
        $returns = Stats::returns(array_slice($closes, -30));
        $vol = max(Stats::std($returns), 0.0005);

        $slopePct = Stats::slope($recent) / $last;             // الاتجاه اليومي
        $momentum = ($last - $closes[count($closes) - 6]) / $closes[count($closes) - 6]; // تغير 5 أيام
        $ma7 = Stats::mean(array_slice($closes, -7));
        $ma21 = Stats::mean(array_slice($closes, -21));
        $cross = ($ma7 - $ma21) / $ma21;

        $score = 0.5 * ($slopePct / $vol) + 0.3 * ($momentum / ($vol * sqrt(5))) + 0.2 * ($cross / $vol);
        $prob = 1 / (1 + exp(-0.8 * $score));
        $prob = Stats::clamp($prob, 0.25, 0.75);

        $expected = $last * (1 + $slopePct);

        return [
            'prob_up' => (int) round($prob * 100),
            'direction' => $prob >= 0.55 ? 'up' : ($prob <= 0.45 ? 'down' : 'flat'),
            'expected' => round($expected),
            'low' => round($expected * (1 - $vol)),
            'high' => round($expected * (1 + $vol)),
            'ma7' => round($ma7),
            'ma21' => round($ma21),
            'signal' => match (true) {
                $ma7 > $ma21 * 1.002 => 'المتوسط القصير فوق الطويل — اتجاه صاعد',
                $ma7 < $ma21 * 0.998 => 'المتوسط القصير تحت الطويل — اتجاه هابط',
                default => 'المتوسطات متقاربة — السوق مستقر',
            },
        ];
    }

    /**
     * اختبار رجعي: كم مرة أصاب النموذج اتجاه اليوم التالي خلال آخر 60 يوماً؟
     *
     * @param  list<float>  $closes
     * @return array{0: ?int, 1: int}
     */
    public function backtest(array $closes, int $days = 60): array
    {
        $hits = $tested = 0;
        $n = count($closes);

        for ($i = max(25, $n - $days); $i < $n; $i++) {
            $prediction = $this->predictFrom(array_slice($closes, 0, $i));
            if ($prediction['direction'] === 'flat') {
                continue;
            }
            $actualUp = $closes[$i] > $closes[$i - 1];
            $tested++;
            $hits += ($prediction['direction'] === 'up') === $actualUp ? 1 : 0;
        }

        return [$tested ? (int) round($hits / $tested * 100) : null, $tested];
    }
}
