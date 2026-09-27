<?php

namespace App\Services\Pricing\Sources;

use App\Models\Instrument;
use App\Models\PriceReading;
use App\Models\Source;
use App\Services\Pricing\PriceAggregator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * مؤشر مساعد: سعر USDT بالدينار من إعلانات منصات P2P (إن توفّرت إعلانات بالدينار العراقي).
 * يُحفظ كأداة مستقلة (usdt) ولا يدخل في سعر الدولار الموازي مباشرة.
 */
class P2PSource implements PriceSource
{
    public function __construct(private readonly PriceAggregator $aggregator) {}

    public function fetch(Source $source): int
    {
        $url = $source->config['url'] ?? 'https://p2p.binance.com/bapi/c2c/v2/friendly/c2c/adv/search';
        $prices = [];

        foreach (['BUY', 'SELL'] as $side) {
            $response = Http::timeout(15)->post($url, [
                'asset' => 'USDT',
                'fiat' => 'IQD',
                'tradeType' => $side,
                'page' => 1,
                'rows' => 10,
            ]);
            $response->throw();

            $values = collect($response->json('data') ?? [])
                ->map(fn ($ad) => (float) ($ad['adv']['price'] ?? 0))
                ->filter(fn ($p) => $p > 0)
                ->values()
                ->all();

            $prices[$side] = $values ? $this->aggregator->median($values) * 100 : null;
        }

        if (! $prices['BUY'] && ! $prices['SELL']) {
            throw new RuntimeException('لا توجد إعلانات USDT بالدينار العراقي حالياً');
        }

        PriceReading::create([
            'instrument_id' => Instrument::byCode('usdt')->id,
            'source_id' => $source->id,
            'buy' => $prices['SELL'] ?? $prices['BUY'],
            'sell' => $prices['BUY'] ?? $prices['SELL'],
            'recorded_at' => now(),
        ]);

        return 1;
    }
}
