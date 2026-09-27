<?php

namespace Tests\Unit;

use App\Models\PriceReading;
use App\Services\Analytics\Forecaster;
use App\Services\Analytics\PredictionScorer;
use App\Services\Pricing\PriceAggregator;
use App\Services\Pricing\PriceService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AggregatorAndForecastTest extends TestCase
{
    private function reading(float $buy, float $sell, int $minutesAgo = 5, int $source = 1): PriceReading
    {
        $r = new PriceReading(['buy' => $buy, 'sell' => $sell, 'recorded_at' => now()->subMinutes($minutesAgo)]);
        $r->source_id = $source;
        $r->setRelation('source', null)->setRelation('user', null);

        return $r;
    }

    public function test_outliers_are_removed_and_weighted_median_is_used(): void
    {
        $agg = new PriceAggregator;
        $readings = collect([
            $this->reading(145000, 145250, 5, 1),
            $this->reading(145100, 145300, 8, 2),
            $this->reading(145050, 145250, 3, 3),
            $this->reading(152000, 152300, 2, 4), // خطأ كتابة واضح
        ]);

        $result = $agg->compute($readings, now());

        $this->assertSame(3, $result['sample_count']);
        $this->assertEqualsWithDelta(145150, $result['mid'], 100);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_single_stale_reading_has_low_confidence(): void
    {
        $result = (new PriceAggregator)->compute(collect([$this->reading(145000, 145000, 50)]), now());

        $this->assertSame('low', $result['confidence']);
    }

    public function test_empty_readings_return_null(): void
    {
        $this->assertNull((new PriceAggregator)->compute(collect(), Carbon::now()));
    }

    public function test_forecast_detects_uptrend_and_stays_within_bounds(): void
    {
        $closes = [];
        for ($i = 0; $i < 40; $i++) {
            $closes[] = 140000 + $i * 120 + ($i % 3) * 30;
        }

        $forecast = (new Forecaster(app(PriceService::class)))->predictFrom($closes);

        $this->assertSame('up', $forecast['direction']);
        $this->assertGreaterThanOrEqual(25, $forecast['prob_up']);
        $this->assertLessThanOrEqual(75, $forecast['prob_up']);
    }

    public function test_prediction_points(): void
    {
        $this->assertSame(100, PredictionScorer::pointsFor(0.0));
        $this->assertSame(50, PredictionScorer::pointsFor(0.5));
        $this->assertSame(0, PredictionScorer::pointsFor(2.0));
    }
}
