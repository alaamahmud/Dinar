<?php

namespace App\Services\Pricing;

use App\Models\City;
use App\Models\Instrument;
use App\Models\PriceSnapshot;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * قراءة الأسعار المحسوبة للعرض: آخر سعر، التغيّر، التاريخ، الافتتاح والإغلاق.
 * غير المشتركين يرون الأسعار بتأخير 15 دقيقة.
 */
class PriceService
{
    public function cutoffFor(?User $viewer): CarbonInterface
    {
        if ($viewer?->hasPlan('pro')) {
            return now();
        }

        return now()->subMinutes((int) config('dinar.free_delay_minutes', 15));
    }

    public function isDelayedFor(?User $viewer): bool
    {
        return ! $viewer?->hasPlan('pro');
    }

    public function latest(string $code, ?int $cityId = null, ?string $noteType = null, ?CarbonInterface $before = null): ?PriceSnapshot
    {
        $instrument = Instrument::byCode($code);

        if ($instrument->has_cities && $cityId === null) {
            $cityId = City::primary()?->id;
        }

        return PriceSnapshot::query()
            ->where('instrument_id', $instrument->id)
            ->where('city_id', $instrument->has_cities ? $cityId : null)
            ->where('note_type', $noteType)
            ->where('computed_at', '<=', $before ?? now())
            ->orderByDesc('computed_at')
            ->first();
    }

    /**
     * آخر سعر مع التغيّر خلال 24 ساعة.
     *
     * @return array{snapshot: PriceSnapshot, instrument: Instrument, change: float, change_pct: float, direction: string}|null
     */
    public function quote(string $code, ?User $viewer = null, ?int $cityId = null, ?string $noteType = null): ?array
    {
        $cutoff = $this->cutoffFor($viewer);
        $snapshot = $this->latest($code, $cityId, $noteType, $cutoff);

        if ($snapshot === null) {
            return null;
        }

        $previous = $this->latest($code, $cityId, $noteType, $snapshot->computed_at->copy()->subDay());
        $change = $previous ? $snapshot->mid - $previous->mid : 0.0;
        $pct = $previous && $previous->mid > 0 ? $change / $previous->mid * 100 : 0.0;

        return [
            'snapshot' => $snapshot,
            'instrument' => Instrument::byCode($code),
            'change' => round($change, 2),
            'change_pct' => round($pct, 2),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
        ];
    }

    public const RANGES = [
        '1d' => ['days' => 1, 'bucket' => 600, 'label' => 'يوم'],
        '7d' => ['days' => 7, 'bucket' => 3600, 'label' => 'أسبوع'],
        '30d' => ['days' => 30, 'bucket' => 6 * 3600, 'label' => 'شهر'],
        '1y' => ['days' => 365, 'bucket' => 86400, 'label' => 'سنة'],
        'all' => ['days' => 36500, 'bucket' => 86400, 'label' => 'الكل'],
    ];

    /**
     * سلسلة أسعار مقسمة على فترات (آخر قيمة في كل فترة).
     *
     * @return list<array{t: string, v: float}>
     */
    public function series(string $code, string $range = '30d', ?int $cityId = null, ?CarbonInterface $until = null, ?string $noteType = null): array
    {
        $config = self::RANGES[$range] ?? self::RANGES['30d'];
        $instrument = Instrument::byCode($code);
        if ($instrument->has_cities && $cityId === null) {
            $cityId = City::primary()?->id;
        }
        $until ??= now();

        $rows = PriceSnapshot::query()
            ->where('instrument_id', $instrument->id)
            ->where('city_id', $instrument->has_cities ? $cityId : null)
            ->where('note_type', $noteType)
            ->whereBetween('computed_at', [$until->copy()->subDays($config['days']), $until])
            ->orderBy('computed_at')
            ->get(['mid', 'computed_at']);

        $buckets = [];
        foreach ($rows as $row) {
            $key = intdiv($row->computed_at->getTimestamp(), $config['bucket']);
            $buckets[$key] = ['t' => $row->computed_at->toIso8601String(), 'v' => (float) $row->mid];
        }

        return array_values($buckets);
    }

    /**
     * الإغلاقات اليومية (آخر سعر في كل يوم).
     *
     * @return Collection<string, float> تاريخ => سعر
     */
    public function dailyCloses(string $code, int $days = 90, ?int $cityId = null, ?CarbonInterface $until = null): Collection
    {
        return collect($this->series($code, $days > 365 ? 'all' : '1y', $cityId, $until))
            ->groupBy(fn ($p) => substr($p['t'], 0, 10))
            ->map(fn ($group) => (float) $group->last()['v'])
            ->sortKeys()
            ->slice(-$days);
    }

    /**
     * سعر الافتتاح والإغلاق لبورصة اليوم.
     *
     * @return array{open: ?float, close: ?float, high: ?float, low: ?float}
     */
    public function dayStats(string $code, ?Carbon $date = null, ?int $cityId = null, ?CarbonInterface $cutoff = null): array
    {
        $date ??= today();
        $instrument = Instrument::byCode($code);
        if ($instrument->has_cities && $cityId === null) {
            $cityId = City::primary()?->id;
        }

        $end = $date->copy()->endOfDay();
        if ($cutoff !== null && $cutoff->lessThan($end)) {
            $end = $cutoff;
        }

        $rows = PriceSnapshot::query()
            ->where('instrument_id', $instrument->id)
            ->where('city_id', $instrument->has_cities ? $cityId : null)
            ->whereNull('note_type')
            ->whereBetween('computed_at', [$date->copy()->startOfDay(), $end])
            ->orderBy('computed_at')
            ->pluck('mid');

        return [
            'open' => $rows->first(),
            'close' => $rows->last(),
            'high' => $rows->max(),
            'low' => $rows->min(),
        ];
    }

    /**
     * سعر الدولار في كل المدن.
     *
     * @return Collection<int, array{city: City, snapshot: ?PriceSnapshot, diff_pct: ?float}>
     */
    public function cityBoard(?User $viewer = null): Collection
    {
        $cutoff = $this->cutoffFor($viewer);
        $primary = $this->latest('usd', null, null, $cutoff);

        return City::orderBy('sort')->get()->map(function (City $city) use ($cutoff, $primary) {
            $snapshot = $this->latest('usd', $city->id, null, $cutoff);

            return [
                'city' => $city,
                'snapshot' => $snapshot,
                'diff_pct' => $snapshot && $primary ? round(($snapshot->mid - $primary->mid) / $primary->mid * 100, 2) : null,
            ];
        });
    }

    /**
     * سعر أداة في تاريخ معيّن (لحاسبات «لو اشتريت» والراتب).
     */
    public function priceAt(string $code, CarbonInterface $at): ?float
    {
        return $this->latest($code, null, null, $at)?->mid
            ?? PriceSnapshot::where('instrument_id', Instrument::byCode($code)->id)
                ->orderBy('computed_at')->value('mid');
    }

    public function usdRate(?User $viewer = null): float
    {
        return (float) ($this->latest('usd', null, null, $this->cutoffFor($viewer))?->mid ?? 145000);
    }
}
