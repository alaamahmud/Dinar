<?php

namespace Tests\Unit;

use App\Services\Pricing\TextPriceParser;
use PHPUnit\Framework\TestCase;

class TextPriceParserTest extends TestCase
{
    private TextPriceParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TextPriceParser;
    }

    public function test_parses_simple_kifah_price(): void
    {
        $result = $this->parser->parse('بورصة الكفاح الآن: 145,250');

        $this->assertCount(1, $result);
        $this->assertSame('baghdad-kifah', $result[0]['city']);
        $this->assertSame(145250.0, $result[0]['buy']);
        $this->assertSame(145250.0, $result[0]['sell']);
    }

    public function test_parses_arabic_indic_digits_and_sides(): void
    {
        $result = $this->parser->parse("سعر الدولار في أربيل\nبيع ١٤٥٥٠٠ شراء ١٤٥٠٠٠");

        $this->assertSame('erbil', $result[0]['city']);
        $this->assertSame(145000.0, $result[0]['buy']);
        $this->assertSame(145500.0, $result[0]['sell']);
    }

    public function test_city_carries_to_following_lines_and_detects_white_notes(): void
    {
        $result = $this->parser->parse("أسعار البصرة\nالدولار 145.750\nالدولار الأبيض 144.100");

        $this->assertCount(2, $result);
        $this->assertSame('basra', $result[1]['city']);
        $this->assertSame('white', $result[1]['note_type']);
        $this->assertSame(144100.0, $result[1]['buy']);
    }

    public function test_per_dollar_price_is_scaled_to_per_hundred(): void
    {
        $result = $this->parser->parse('صرف الدولار الواحد 1452 دينار');

        $this->assertSame(145200.0, $result[0]['buy']);
    }

    public function test_ignores_years_phone_numbers_and_text_without_context(): void
    {
        $this->assertSame([], $this->parser->parse('مبروك للفائزين 145000'));
        $this->assertSame([], $this->parser->parse('سعر الدولار اليوم 2026 للاستفسار 07701452000'));
    }
}
