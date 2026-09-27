<?php

namespace App\Services\Analytics;

use App\Models\NewsItem;
use App\Models\PriceReading;
use App\Services\Pricing\PriceService;
use Carbon\CarbonInterface;

/**
 * مؤشر الخوف على الدينار (0–100):
 * 0 = هدوء تام، 100 = ذعر واندفاع نحو الدولار.
 * يجمع: الزخم، التذبذب، نشاط البلاغات والقنوات، ونبرة الأخبار.
 */
class FearIndex
{
    public function __construct(private readonly PriceService $prices) {}

    /**
     * @return array{value: int, label: string, color: string, components: array<string, array{label: string, value: int}>}
     */
    public function compute(?CarbonInterface $at = null): array
    {
        $at ??= now();
        $closes = $this->prices->dailyCloses('usd', 90, null, $at)->values()->all();
        $hourly = array_column($this->prices->series('usd', '7d', null, $at), 'v');

        // 1) الزخم: تغير 7 أيام بين -3% و +3%
        $momentum = 50.0;
        if (count($closes) >= 8) {
            $change = ($closes[count($closes) - 1] - $closes[count($closes) - 8]) / $closes[count($closes) - 8];
            $momentum = Stats::scale($change, -0.03, 0.03);
        }

        // 2) التذبذب: تذبذب الأسبوع مقارنة بمتوسط 90 يوماً
        $volatility = 50.0;
        $dailyVol = Stats::std(Stats::returns($closes));
        $weekVol = Stats::std(Stats::returns(array_slice($closes, -8)));
        if ($dailyVol > 0) {
            $volatility = Stats::scale($weekVol / $dailyVol, 0.5, 2.0);
        }

        // 3) النشاط: القراءات والبلاغات آخر 24 ساعة مقارنة بالمعدل
        $last24 = PriceReading::whereBetween('recorded_at', [$at->copy()->subDay(), $at])->count();
        $avg = PriceReading::whereBetween('recorded_at', [$at->copy()->subDays(30), $at])->count() / 30;
        $activity = $avg > 0 ? Stats::scale($last24 / $avg, 0.5, 2.5) : 50.0;

        // 4) الأخبار: نسبة الأخبار التي قد ترفع الدولار آخر 3 أيام
        $news = NewsItem::whereBetween('published_at', [$at->copy()->subDays(3), $at])->get(['impact', 'impact_strength']);
        $up = $news->where('impact', 'up')->sum(fn ($n) => max(1, $n->impact_strength));
        $down = $news->where('impact', 'down')->sum(fn ($n) => max(1, $n->impact_strength));
        $newsScore = ($up + $down) > 0 ? $up / ($up + $down) * 100 : 50.0;

        $components = [
            'momentum' => ['label' => 'اتجاه السعر', 'value' => (int) round($momentum)],
            'volatility' => ['label' => 'حدة التذبذب', 'value' => (int) round($volatility)],
            'activity' => ['label' => 'نشاط السوق والقنوات', 'value' => (int) round($activity)],
            'news' => ['label' => 'نبرة الأخبار', 'value' => (int) round($newsScore)],
        ];

        $value = (int) round(0.35 * $momentum + 0.25 * $volatility + 0.15 * $activity + 0.25 * $newsScore);

        [$label, $color] = match (true) {
            $value < 25 => ['هدوء تام', '#16a34a'],
            $value < 45 => ['هدوء', '#65a30d'],
            $value < 56 => ['محايد', '#64748b'],
            $value < 75 => ['قلق', '#ea580c'],
            default => ['ذعر', '#dc2626'],
        };

        return compact('value', 'label', 'color', 'components');
    }
}
