<?php

namespace App\Services\Analytics;

use App\Models\BasketItem;
use App\Models\BasketPrice;
use Illuminate\Support\Collection;

/**
 * مؤشر سلة المواطن: تضخم شعبي يُبنى من أسعار يبلّغ عنها الناس.
 * الأساس = 100 في أول شهر متوفر.
 */
class BasketIndex
{
    /**
     * @return array{months: list<string>, index: list<float>, items: Collection<int, array<string, mixed>>, change_pct: ?float}
     */
    public function compute(?int $cityId = null, int $months = 12): array
    {
        $items = BasketItem::orderBy('sort')->get();
        $prices = BasketPrice::query()
            ->when($cityId, fn ($q) => $q->where('city_id', $cityId))
            ->where('reported_at', '>=', now()->subMonths($months)->startOfMonth())
            ->get(['basket_item_id', 'price', 'reported_at']);

        $byMonth = $prices->groupBy(fn ($p) => $p->reported_at->format('Y-m'))->sortKeys();
        $monthKeys = $byMonth->keys()->all();

        $medians = [];
        foreach ($byMonth as $month => $rows) {
            foreach ($rows->groupBy('basket_item_id') as $itemId => $itemRows) {
                $values = $itemRows->pluck('price')->sort()->values();
                $medians[$month][$itemId] = (float) $values[intdiv($values->count(), 2)];
            }
        }

        $base = $monthKeys[0] ?? null;
        $index = [];
        foreach ($monthKeys as $month) {
            $sum = $weights = 0.0;
            foreach ($items as $item) {
                $basePrice = $medians[$base][$item->id] ?? null;
                $price = $medians[$month][$item->id] ?? null;
                if ($basePrice && $price) {
                    $sum += $item->weight * $price / $basePrice;
                    $weights += $item->weight;
                }
            }
            $index[] = $weights > 0 ? round($sum / $weights * 100, 1) : 100.0;
        }

        $last = end($monthKeys) ?: null;
        $itemRows = $items->map(fn (BasketItem $item) => [
            'item' => $item,
            'price' => $last ? ($medians[$last][$item->id] ?? null) : null,
            'base' => $base ? ($medians[$base][$item->id] ?? null) : null,
        ])->map(fn ($row) => $row + [
            'change_pct' => $row['price'] && $row['base'] ? round(($row['price'] - $row['base']) / $row['base'] * 100, 1) : null,
        ]);

        return [
            'months' => $monthKeys,
            'index' => $index,
            'items' => $itemRows,
            'change_pct' => $index ? round(end($index) - 100, 1) : null,
        ];
    }
}
