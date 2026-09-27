<?php

namespace App\Services\Pricing;

use App\Models\City;
use App\Models\Instrument;
use App\Models\PriceReading;
use App\Models\PriceSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * يحوّل القراءات الخام من كل المصادر إلى سعر واحد موثوق:
 * 1) يجمع قراءات آخر ساعة
 * 2) يستبعد الشاذ (أبعد من 1.5% عن الوسيط)
 * 3) يأخذ الوسيط الموزون (وزن المصدر × ثقة المبلّغ × حداثة القراءة)
 * 4) يحدد مستوى الثقة
 */
class PriceAggregator
{
    public function aggregate(Instrument $instrument, ?City $city = null, ?string $noteType = null, ?CarbonInterface $at = null): ?PriceSnapshot
    {
        $at ??= now();
        $window = (int) config('dinar.aggregation_window_minutes', 60);

        $readings = PriceReading::query()
            ->with(['source', 'user'])
            ->where('instrument_id', $instrument->id)
            ->where('city_id', $city?->id)
            ->where('note_type', $noteType)
            ->whereBetween('recorded_at', [$at->copy()->subMinutes($window), $at])
            ->get();

        $result = $this->compute($readings, $at);

        if ($result === null) {
            return null;
        }

        return PriceSnapshot::create([
            'instrument_id' => $instrument->id,
            'city_id' => $city?->id,
            'note_type' => $noteType,
            'computed_at' => $at,
            ...$result,
        ]);
    }

    /**
     * الحساب الصافي (بدون قاعدة بيانات) — يسهل اختباره.
     *
     * @param  Collection<int, PriceReading>  $readings
     * @return array{buy: float, sell: float, mid: float, confidence: string, sample_count: int}|null
     */
    public function compute(Collection $readings, CarbonInterface $at): ?array
    {
        $points = $readings
            ->map(fn (PriceReading $r) => [
                'buy' => $r->buy,
                'sell' => $r->sell,
                'mid' => $r->mid(),
                'weight' => $this->weight($r, $at),
                'source' => $r->source_id ?? 'user-'.$r->user_id,
                'age' => $r->recorded_at->diffInMinutes($at, true),
            ])
            ->filter(fn ($p) => $p['mid'] > 0)
            ->values();

        if ($points->isEmpty()) {
            return null;
        }

        $median = $this->median($points->pluck('mid')->all());
        $threshold = (float) config('dinar.outlier_threshold', 0.015);

        $kept = $points->filter(fn ($p) => abs($p['mid'] - $median) / $median <= $threshold)->values();
        if ($kept->isEmpty()) {
            $kept = $points;
        }

        $mid = $this->weightedMedian($kept->map(fn ($p) => [$p['mid'], $p['weight']])->all());

        $buys = $kept->filter(fn ($p) => $p['buy'] !== null && $p['buy'] > 0)->map(fn ($p) => [$p['buy'], $p['weight']])->all();
        $sells = $kept->filter(fn ($p) => $p['sell'] !== null && $p['sell'] > 0)->map(fn ($p) => [$p['sell'], $p['weight']])->all();

        $buy = $buys ? $this->weightedMedian(array_values($buys)) : $mid;
        $sell = $sells ? $this->weightedMedian(array_values($sells)) : $mid;
        if ($sell < $buy) {
            [$buy, $sell] = [$sell, $buy];
        }

        return [
            'buy' => round($buy, 2),
            'sell' => round($sell, 2),
            'mid' => round(($buy + $sell) / 2, 2),
            'confidence' => $this->confidence($kept),
            'sample_count' => $kept->count(),
        ];
    }

    private function weight(PriceReading $reading, CarbonInterface $at): float
    {
        $weight = (float) ($reading->source?->weight ?? 1);

        if ($reading->user_id !== null && $reading->user !== null) {
            $weight *= max(0.1, (float) $reading->user->trust_score) * 2;
        }

        // القراءة تفقد نصف وزنها كل 30 دقيقة
        $age = $reading->recorded_at->diffInMinutes($at, true);

        return $weight * pow(0.5, $age / 30);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $kept
     */
    private function confidence(Collection $kept): string
    {
        $count = $kept->count();
        $sources = $kept->pluck('source')->unique()->count();
        $freshest = $kept->min('age');
        $mids = $kept->pluck('mid')->all();
        $spread = $count > 1 ? (max($mids) - min($mids)) / $this->median($mids) : 0;

        if ($freshest > 45) {
            return 'low';
        }
        if ($count >= 3 && $sources >= 2 && $spread < 0.005) {
            return 'high';
        }
        if ($count >= 2 || $spread < 0.01) {
            return $count >= 2 ? 'medium' : 'low';
        }

        return 'low';
    }

    /**
     * @param  list<float>  $values
     */
    public function median(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $pairs  [القيمة, الوزن]
     */
    public function weightedMedian(array $pairs): float
    {
        usort($pairs, fn ($a, $b) => $a[0] <=> $b[0]);
        $total = array_sum(array_column($pairs, 1));
        if ($total <= 0) {
            return $this->median(array_column($pairs, 0));
        }

        $cumulative = 0;
        foreach ($pairs as [$value, $weight]) {
            $cumulative += $weight;
            if ($cumulative >= $total / 2) {
                return (float) $value;
            }
        }

        return (float) end($pairs)[0];
    }
}
