<?php

namespace App\Services\Pricing;

use App\Models\City;
use App\Models\Instrument;
use App\Models\Source;
use App\Services\Pricing\Sources\SourceRegistry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * الدورة الكاملة: جلب من المصادر ← حساب الأسعار ← الأسعار المشتقة.
 */
class MarketPipeline
{
    public function __construct(
        private readonly SourceRegistry $registry,
        private readonly PriceAggregator $aggregator,
        private readonly DerivedPrices $derived,
    ) {}

    /**
     * @return array<string, int|string> عدد القراءات الجديدة لكل مصدر
     */
    public function fetchAll(): array
    {
        $report = [];

        foreach (Source::where('enabled', true)->get() as $source) {
            $driver = $this->registry->for($source);
            if ($driver === null) {
                continue;
            }

            try {
                $count = $driver->fetch($source);
                $source->forceFill([
                    'last_fetched_at' => now(),
                    'last_error' => null,
                    'readings_count' => $source->readings_count + $count,
                ])->save();
                $report[$source->name] = $count;
            } catch (Throwable $e) {
                Log::warning("Source {$source->name} failed: {$e->getMessage()}");
                $source->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 500), 'last_fetched_at' => now()])->save();
                $report[$source->name] = 'خطأ: '.$e->getMessage();
            }
        }

        return $report;
    }

    public function aggregateAll(?CarbonInterface $at = null): int
    {
        $at ??= now();
        $count = 0;
        $cities = City::all();

        foreach (Instrument::all() as $instrument) {
            if ($instrument->has_cities) {
                foreach ($cities as $city) {
                    foreach ([null, 'white', 'small'] as $noteType) {
                        $count += $this->aggregator->aggregate($instrument, $city, $noteType, $at) ? 1 : 0;
                    }
                }
            } else {
                $count += $this->aggregator->aggregate($instrument, null, null, $at) ? 1 : 0;
            }
        }

        $this->derived->compute($at);

        return $count;
    }
}
