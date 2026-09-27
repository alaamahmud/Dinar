<?php

namespace App\Services\News;

/**
 * تحليل مجاني بالكلمات المفتاحية — يعمل دائماً، وهو البديل عند إيقاف الذكاء الاصطناعي.
 */
class RuleBasedAnalyzer
{
    /** كلمات ترتبط عادة بارتفاع الدولار أمام الدينار */
    public const UP = [
        'عقوبات' => 3, 'عقوبة' => 3, 'تشديد' => 2, 'حظر' => 2, 'منع' => 1, 'قيود' => 2,
        'انخفاض مبيعات' => 2, 'تراجع مبيعات' => 2, 'تهريب' => 2, 'أزمة' => 2, 'توتر' => 2,
        'الخزانة الأمريكية' => 2, 'الفيدرالي' => 1, 'تأخر الرواتب' => 2, 'تأخير الرواتب' => 2,
        'عجز' => 2, 'انهيار' => 3, 'ارتفاع الدولار' => 2, 'تصعيد' => 2, 'غرامة' => 2,
    ];

    /** كلمات ترتبط عادة بانخفاض الدولار */
    public const DOWN = [
        'زيادة مبيعات' => 2, 'ارتفاع مبيعات' => 2, 'تسهيل' => 2, 'تسهيلات' => 2, 'رفع القيود' => 3,
        'ضخ' => 2, 'انخفاض الدولار' => 2, 'تراجع الدولار' => 2, 'اتفاق' => 1, 'استقرار' => 1,
        'الاحتياطي' => 1, 'إطلاق الرواتب' => 1, 'تمويل الرواتب' => 1, 'تعزيز' => 1, 'انفراج' => 2,
    ];

    /**
     * @return array{summary: string, impact: string, impact_strength: int}
     */
    public function analyze(string $title, ?string $body = null): array
    {
        $text = $title.' '.($body ?? '');
        $up = $this->score($text, self::UP);
        $down = $this->score($text, self::DOWN);
        $net = $up - $down;

        return [
            'summary' => $this->summarize($title, $body),
            'impact' => $net > 0 ? 'up' : ($net < 0 ? 'down' : 'neutral'),
            'impact_strength' => (int) min(3, abs($net)),
        ];
    }

    /**
     * @param  array<string, int>  $words
     */
    private function score(string $text, array $words): int
    {
        $score = 0;
        foreach ($words as $word => $weight) {
            if (mb_strpos($text, $word) !== false) {
                $score += $weight;
            }
        }

        return $score;
    }

    private function summarize(string $title, ?string $body): string
    {
        if (! $body) {
            return $title;
        }
        $first = preg_split('/(?<=[.!؟?])\s+/u', trim(strip_tags($body)))[0] ?? $title;

        return mb_strlen($first) > 180 ? mb_substr($first, 0, 177).'…' : $first;
    }
}
