<x-layouts.app title="العملات">
    <h1 class="text-2xl font-bold">🌍 أسعار العملات في السوق العراقي</h1>
    <p class="text-muted text-sm mt-1 mb-5">العملات التي يحتاجها الزوار والمسافرون والتجار — بسعر السوق المحلي.</p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'usd' => ['💵 الدولار — السوق الموازي', 'السعر الفعلي في البورصات'],
            'usd_cbi' => ['🏦 الدولار — السعر الرسمي', 'سعر البنك المركزي العراقي'],
            'usdt' => ['₮ الدولار الرقمي USDT', 'مؤشر مساعد من منصات P2P — ليس سعر البورصة'],
            'irr' => ['🇮🇷 التومان الإيراني', 'لزوار إيران: كم تومان تحصل مقابل 1,000 دينار'],
            'try' => ['🇹🇷 الليرة التركية', 'للمسافرين إلى تركيا'],
            'sar' => ['🇸🇦 الريال السعودي', 'للمعتمرين والحجاج — مربوط بالدولار'],
            'jod' => ['🇯🇴 الدينار الأردني', 'مربوط بالدولار'],
        ] as $code => [$label, $hint])
            @if ($quotes[$code])
                <div class="flex flex-col gap-2">
                    <x-quote-card :quote="$quotes[$code]" :label="$label" :up-is-bad="$code !== 'irr'" class="flex-1" />
                    <div class="text-xs text-muted px-1">{{ $hint }}</div>
                </div>
            @endif
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-2 mt-6">
        @foreach (['try' => 'الليرة التركية', 'irr' => 'التومان الإيراني'] as $code => $label)
            <div class="card card-pad" data-price-chart data-code="{{ $code }}" data-range="1y" data-color="#0f9f7f">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="section-title">{{ $label }}</h2>
                    <div class="flex gap-1 text-sm">
                        @foreach (['30d' => 'شهر', '1y' => 'سنة'] as $key => $r)<button data-range="{{ $key }}" class="nav-link [&.active]:bg-brand-600 [&.active]:text-white">{{ $r }}</button>@endforeach
                    </div>
                </div>
                <div class="relative h-56"><canvas></canvas>
                    <div data-chart-lock class="hidden absolute inset-0 grid place-items-center"><a href="{{ route('pricing') }}" class="btn btn-accent">💎 سنة كاملة في برو</a></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-pad mt-6 text-sm text-muted leading-7">
        ℹ️ الريال السعودي والدينار الأردني مربوطان بالدولار، لذا يُحسبان من سعر الدولار الموازي مباشرة.
        الليرة التركية والتومان يُحسبان من أسعار عالمية تقريبية (قابلة للتعديل من الإدارة) ما لم تتوفر قراءات مباشرة من السوق.
    </div>
</x-layouts.app>
