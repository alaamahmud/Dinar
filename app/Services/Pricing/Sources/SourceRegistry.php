<?php

namespace App\Services\Pricing\Sources;

use App\Models\Source;

class SourceRegistry
{
    /** @var array<string, class-string<PriceSource>> */
    private array $drivers = [
        'telegram' => TelegramChannelSource::class,
        'gold_api' => GoldApiSource::class,
        'p2p' => P2PSource::class,
        'cbi' => CbiSource::class,
    ];

    public function for(Source $source): ?PriceSource
    {
        $class = $this->drivers[$source->type] ?? null;

        return $class ? app($class) : null;
    }
}
