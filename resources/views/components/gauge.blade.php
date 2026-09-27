@props(['value', 'color', 'label'])
@php
    // نصف دائرة: 0 على اليسار (هدوء) و100 على اليمين (ذعر)
    $angle = M_PI * (1 - $value / 100);
    $x = round(100 + 70 * cos($angle), 1);
    $y = round(100 - 70 * sin($angle), 1);
@endphp
<svg viewBox="0 0 200 112" class="w-full max-w-[240px] mx-auto" role="img" aria-label="المؤشر {{ $value }} — {{ $label }}">
    <defs>
        <linearGradient id="gauge-grad" x1="0" x2="1" y1="0" y2="0">
            <stop offset="0%" stop-color="#16a34a"/><stop offset="50%" stop-color="#ca8a04"/><stop offset="100%" stop-color="#dc2626"/>
        </linearGradient>
    </defs>
    <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="url(#gauge-grad)" stroke-width="14" stroke-linecap="round"/>
    <line x1="100" y1="100" x2="{{ $x }}" y2="{{ $y }}" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
    <circle cx="100" cy="100" r="7" fill="currentColor"/>
</svg>
