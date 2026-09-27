<?php

namespace App\Services\Pricing;

/**
 * يستخرج أسعار الدولار من نصوص عربية حرة (منشورات قنوات تيليجرام مثلاً).
 *
 * أمثلة يفهمها:
 *   "بورصة الكفاح الآن: 145,250"
 *   "أربيل بيع ١٤٥٥٠٠ شراء ١٤٥٠٠٠"
 *   "الدولار الأبيض 144.750"
 */
class TextPriceParser
{
    /** كلمات مفتاحية => slug المدينة */
    public const CITY_KEYWORDS = [
        'الكفاح' => 'baghdad-kifah',
        'الحارثية' => 'baghdad-harthiya',
        'الحارثيه' => 'baghdad-harthiya',
        'بغداد' => 'baghdad-kifah',
        'أربيل' => 'erbil',
        'اربيل' => 'erbil',
        'البصرة' => 'basra',
        'البصره' => 'basra',
        'النجف' => 'najaf',
        'الموصل' => 'mosul',
        'كربلاء' => 'karbala',
        'السليمانية' => 'sulaymaniyah',
        'السليمانيه' => 'sulaymaniyah',
    ];

    public const CONTEXT_WORDS = ['دولار', 'الدولار', '$', 'بورصة', 'بورصه', 'الصرف', 'صرف', 'البورصة', 'سعر'];

    public function __construct(
        private readonly int $min = 100000,
        private readonly int $max = 250000,
    ) {}

    /**
     * @return list<array{city: ?string, buy: ?float, sell: ?float, note_type: ?string}>
     */
    public function parse(string $text): array
    {
        $text = $this->normalize($text);

        if (! $this->hasContext($text)) {
            return [];
        }

        $results = [];
        $currentCity = null;

        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $city = $this->detectCity($line);
            if ($city !== null) {
                $currentCity = $city;
            }

            $numbers = $this->extractNumbers($line);
            if ($numbers === []) {
                continue;
            }

            $noteType = $this->detectNoteType($line);
            [$buy, $sell] = $this->detectSides($line, $numbers);

            $results[] = [
                'city' => $currentCity,
                'buy' => $buy,
                'sell' => $sell,
                'note_type' => $noteType,
            ];
        }

        return $results;
    }

    public function normalize(string $text): string
    {
        $text = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٬' => ',', '٫' => '.', 'ـ' => '',
        ]);

        return $text;
    }

    private function hasContext(string $text): bool
    {
        foreach (self::CONTEXT_WORDS as $word) {
            if (mb_strpos($text, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    private function detectCity(string $line): ?string
    {
        foreach (self::CITY_KEYWORDS as $keyword => $slug) {
            if (mb_strpos($line, $keyword) !== false) {
                return $slug;
            }
        }

        return null;
    }

    private function detectNoteType(string $line): ?string
    {
        if (preg_match('/(الأبيض|الابيض|البيضاء|ابيض|أبيض)/u', $line)) {
            return 'white';
        }
        if (preg_match('/(الفئات الصغيرة|فئات صغيرة|الصغيرة|الفكة|فكة)/u', $line)) {
            return 'small';
        }

        return null;
    }

    /**
     * @return list<array{value: float, offset: int}>
     */
    public function extractNumbers(string $line): array
    {
        // (?<!\d) و (?!\d) تمنع التقاط أجزاء من أرقام الهواتف الطويلة
        preg_match_all('/(?<![\d,.])(?:\d{1,3}(?:[,.]\d{3})+|\d{4,6})(?![\d])/u', $line, $matches, PREG_OFFSET_CAPTURE);

        $numbers = [];
        foreach ($matches[0] as [$raw, $offset]) {
            $value = (float) str_replace([',', '.'], '', $raw);

            // تجاهل ما يبدو كسنة (مثل 2026)
            if (strlen($raw) === 4 && $value >= 1900 && $value <= 2100) {
                continue;
            }

            // سعر الدولار الواحد (مثل 1452) يُحوّل إلى سعر 100 دولار
            if ($value >= $this->min / 100 && $value <= $this->max / 100) {
                $value *= 100;
            }

            if ($value >= $this->min && $value <= $this->max) {
                $numbers[] = ['value' => $value, 'offset' => $offset];
            }
        }

        return $numbers;
    }

    /**
     * @param  list<array{value: float, offset: int}>  $numbers
     * @return array{0: ?float, 1: ?float}
     */
    private function detectSides(string $line, array $numbers): array
    {
        $buyPos = $this->wordPos($line, ['شراء', 'الشراء']);
        $sellPos = $this->wordPos($line, ['بيع', 'البيع']);

        if (count($numbers) >= 2 && $buyPos !== null && $sellPos !== null) {
            $buy = $this->nearest($numbers, $buyPos);
            $sell = $this->nearest($numbers, $sellPos);

            return [$buy, $sell];
        }

        if (count($numbers) >= 2) {
            $values = array_column($numbers, 'value');

            return [min($values), max($values)];
        }

        $value = $numbers[0]['value'];

        if ($sellPos !== null && $buyPos === null) {
            return [null, $value];
        }
        if ($buyPos !== null && $sellPos === null) {
            return [$value, null];
        }

        return [$value, $value];
    }

    /**
     * @param  list<string>  $words
     */
    private function wordPos(string $line, array $words): ?int
    {
        foreach ($words as $word) {
            $pos = strpos($line, $word);
            if ($pos !== false) {
                return $pos;
            }
        }

        return null;
    }

    /**
     * أقرب رقم يأتي بعد الكلمة (أو أقرب رقم عموماً).
     *
     * @param  list<array{value: float, offset: int}>  $numbers
     */
    private function nearest(array $numbers, int $pos): float
    {
        usort($numbers, function ($a, $b) use ($pos) {
            $da = $a['offset'] >= $pos ? $a['offset'] - $pos : ($pos - $a['offset']) * 2;
            $db = $b['offset'] >= $pos ? $b['offset'] - $pos : ($pos - $b['offset']) * 2;

            return $da <=> $db;
        });

        return $numbers[0]['value'];
    }
}
