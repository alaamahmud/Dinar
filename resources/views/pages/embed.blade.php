<!DOCTYPE html>
<html lang="ar" dir="rtl" class="{{ $theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نبض الدينار</title>
    @vite(['resources/css/app.css'])
</head>
<body style="background: var(--surface)" class="p-3 text-sm">
    <div class="flex justify-between items-center mb-2">
        <a href="{{ route('home') }}" target="_blank" class="font-bold text-brand-600">نبض الدينار</a>
        <span class="text-[11px] text-muted">{{ now()->format('H:i') }}</span>
    </div>
    @foreach (['usd' => '💵 100 دولار (الكفاح)', 'gold21' => '🥇 مثقال عيار 21'] as $code => $label)
        @if ($quotes[$code])
            <div class="flex justify-between items-center py-2 border-b border-soft last:border-0">
                <span>{{ $label }}</span>
                <span class="text-left"><b class="num text-base">{{ money($quotes[$code]['snapshot']->mid) }}</b>
                <span class="num text-xs block {{ trend_class($quotes[$code]['change_pct'], $code === 'usd') }}">{{ pct($quotes[$code]['change_pct']) }}</span></span>
            </div>
        @endif
    @endforeach
    <div class="text-[10px] text-muted mt-1">أسعار استرشادية متأخرة 15 دقيقة</div>
</body>
</html>
