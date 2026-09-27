<?php

namespace Tests\Unit;

use App\Services\Pricing\GoldCalculator;
use Tests\TestCase;

class GoldCalculatorTest extends TestCase
{
    public function test_mithqal_price_formula(): void
    {
        // أونصة 3110.35$ = 100$ للغرام عيار 24، والدولار 1450 دينار
        $price = (new GoldCalculator)->mithqalPrice(24, 3110.35, 145000, 0);

        $this->assertEqualsWithDelta(725000, $price, 1);
        $this->assertEqualsWithDelta(725000 * 21 / 24, (new GoldCalculator)->mithqalPrice(21, 3110.35, 145000, 0), 1);
    }
}
