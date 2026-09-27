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

    /**
     * منشورات حقيقية من قناة «بورصة الكفاح» (t.me/borsat_alkfah) كما تظهر في التطبيق.
     */
    public function test_parses_kifah_channel_format(): void
    {
        $cases = [
            "🔷 كفاح\n• مطلوب: 1555.50\n• معروض: 1555.50" => ['baghdad-kifah', 155550.0, 155550.0],
            "🔷 حارثية\n• مطلوب: 1555.00\n• معروض: 1555.50" => ['baghdad-harthiya', 155500.0, 155550.0],
            "🔷 سموأل\n• مطلوب: 1555.50\n• معروض: 1556.00" => ['baghdad-samawal', 155550.0, 155600.0],
            "🔷 البصرة\n• مطلوب: 1555.00\n• معروض: 1556.00" => ['basra', 155500.0, 155600.0],
            "🔷 أربيل\n• مطلوب: 1555.50\n• معروض: 1556.00" => ['erbil', 155550.0, 155600.0],
            "🔷 سليمانية\n• مطلوب: 1558.50\n• معروض: 1558.50" => ['sulaymaniyah', 155850.0, 155850.0],
            "🔷 دهوك\n• مطلوب: 1559.00\n• معروض: 1559.50" => ['duhok', 155900.0, 155950.0],
        ];

        foreach ($cases as $text => [$city, $buy, $sell]) {
            $result = $this->parser->parse($text);

            $this->assertCount(1, $result, $text);
            $this->assertSame($city, $result[0]['city'], $text);
            $this->assertSame($buy, $result[0]['buy'], $text);
            $this->assertSame($sell, $result[0]['sell'], $text);
        }
    }

    public function test_thousands_with_dot_are_not_treated_as_decimals(): void
    {
        $this->assertSame(145750.0, $this->parser->parse('الكفاح 145.750')[0]['buy']);
    }
}
