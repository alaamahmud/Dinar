<?php

use App\Models\Instrument;

if (! function_exists('money')) {
    /** تنسيق رقم بفواصل الآلاف */
    function money(float|int|null $value, int $decimals = 0): string
    {
        return $value === null ? '—' : number_format((float) $value, $decimals);
    }
}

if (! function_exists('price')) {
    /** تنسيق سعر أداة بعدد المنازل العشرية المناسب لها */
    function price(float|int|null $value, Instrument|string $instrument): string
    {
        $decimals = $instrument instanceof Instrument ? $instrument->decimals : Instrument::byCode($instrument)->decimals;

        return money($value, $decimals);
    }
}

if (! function_exists('pct')) {
    function pct(?float $value, int $decimals = 2, bool $sign = true): string
    {
        if ($value === null) {
            return '—';
        }
        $prefix = $sign && $value > 0 ? '+' : '';

        return $prefix.number_format($value, $decimals).'%';
    }
}

if (! function_exists('trend_class')) {
    /** لون التغيّر: ارتفاع الدولار أحمر (سيئ للدينار)، انخفاضه أخضر */
    function trend_class(?float $value, bool $upIsBad = true): string
    {
        if (! $value) {
            return 'text-muted';
        }

        return ($value > 0) === $upIsBad ? 'text-down' : 'text-up';
    }
}
