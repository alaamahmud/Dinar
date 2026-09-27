<?php

namespace App\Services\Pricing\Sources;

use App\Models\Source;

interface PriceSource
{
    /**
     * يجلب القراءات الجديدة ويحفظها، ويعيد عددها.
     */
    public function fetch(Source $source): int;
}
