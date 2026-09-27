<?php

namespace App\Services\Analytics;

final class Stats
{
    /** @param list<float> $values */
    public static function mean(array $values): float
    {
        return $values ? array_sum($values) / count($values) : 0.0;
    }

    /** @param list<float> $values */
    public static function std(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean = self::mean($values);

        return sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / ($n - 1));
    }

    /**
     * التغيرات النسبية بين القيم المتتالية.
     *
     * @param  list<float>  $values
     * @return list<float>
     */
    public static function returns(array $values): array
    {
        $out = [];
        for ($i = 1, $n = count($values); $i < $n; $i++) {
            if ($values[$i - 1] > 0) {
                $out[] = ($values[$i] - $values[$i - 1]) / $values[$i - 1];
            }
        }

        return $out;
    }

    /**
     * ميل خط الانحدار الخطي (لكل خطوة).
     *
     * @param  list<float>  $values
     */
    public static function slope(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $xMean = ($n - 1) / 2;
        $yMean = self::mean($values);
        $num = $den = 0.0;
        foreach (array_values($values) as $x => $y) {
            $num += ($x - $xMean) * ($y - $yMean);
            $den += ($x - $xMean) ** 2;
        }

        return $den > 0 ? $num / $den : 0.0;
    }

    public static function clamp(float $v, float $min, float $max): float
    {
        return max($min, min($max, $v));
    }

    /** يحوّل قيمة من مدى [a,b] إلى [0,100] */
    public static function scale(float $v, float $a, float $b): float
    {
        return self::clamp(($v - $a) / ($b - $a) * 100, 0, 100);
    }
}
