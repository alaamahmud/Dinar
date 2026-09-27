<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="300">
    <title>شاشة الأسعار — {{ $owner->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen p-8" data-refresh="20000" data-live-query="display={{ $owner->display_token }}" style="background: radial-gradient(circle at top, #0b3d33, #0c1311 60%)">
    <div class="flex justify-between items-center mb-8">
        <div>
            <div class="text-4xl font-bold">{{ $owner->name }}</div>
            <div class="text-muted text-lg mt-1">أسعار اليوم · {{ now()->translatedFormat('l j F') }}</div>
        </div>
        <div class="text-left">
            <div class="num text-5xl font-bold text-accent-400" data-live-clock>{{ now()->format('H:i:s') }}</div>
            <div class="text-muted">يتحدث تلقائياً</div>
        </div>
    </div>

    @php($usd = $quotes['usd'])
    @if ($usd)
        <div class="grid grid-cols-3 gap-6 mb-6">
            <div class="card p-8 text-center col-span-1"><div class="text-2xl text-muted">💵 100 دولار — شراء</div><div class="num text-7xl font-bold mt-3" data-live="usd" data-field="buy">{{ money($usd['snapshot']->buy) }}</div></div>
            <div class="card p-8 text-center col-span-1"><div class="text-2xl text-muted">💵 100 دولار — بيع</div><div class="num text-7xl font-bold mt-3" data-live="usd" data-field="sell">{{ money($usd['snapshot']->sell) }}</div></div>
            <div class="card p-8 text-center col-span-1 grid gap-3 text-2xl">
                @foreach (['white' => '⚪ الأبيض', 'small' => '🪙 الفئات الصغيرة'] as $t => $l)
                    @if ($notes[$t])<div class="flex justify-between"><span class="text-muted">{{ $l }}</span><b class="num">{{ money($notes[$t]['snapshot']->mid) }}</b></div>@endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-3 gap-6 mb-6">
        @foreach (['gold24' => 'ذهب عيار 24', 'gold21' => 'ذهب عيار 21', 'gold18' => 'ذهب عيار 18'] as $code => $label)
            @if ($quotes[$code])
                <div class="card p-6 text-center" style="border-color: #2563eb66"><div class="text-xl text-accent-400">🥇 {{ $label }} (مثقال)</div><div class="num text-5xl font-bold mt-2" data-live="{{ $code }}">{{ money($quotes[$code]['snapshot']->mid) }}</div></div>
            @endif
        @endforeach
    </div>

    <div class="grid grid-cols-3 gap-6">
        @foreach (['try' => '🇹🇷 الليرة التركية', 'irr' => '🇮🇷 التومان (لكل 1,000 د.ع)', 'sar' => '🇸🇦 الريال السعودي'] as $code => $label)
            @if ($quotes[$code])
                <div class="card p-5 flex justify-between items-center text-2xl"><span class="text-muted">{{ $label }}</span><b class="num" data-live="{{ $code }}">{{ price($quotes[$code]['snapshot']->mid, $code) }}</b></div>
            @endif
        @endforeach
    </div>
</body>
</html>
