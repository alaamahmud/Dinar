<x-layouts.app>
    @if ($anomaly)
        <div class="card card-pad mb-5 flex items-center gap-3 font-semibold" style="border-color: var(--down); background: color-mix(in srgb, var(--down) 8%, var(--surface))">
            <span class="text-2xl">⚡</span>
            <span>{{ $anomaly['message'] }}</span>
        </div>
    @endif

    @php($usd = $quotes['usd'])
    @php($cbi = $quotes['usd_cbi'])

    {{-- البطاقة الرئيسية + الرسم --}}
    <section class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 card overflow-hidden">
            <div class="card-pad pb-0 flex flex-wrap items-start gap-6 justify-between">
                <div>
                    <div class="text-sm text-muted font-semibold mb-1">💵 سعر 100 دولار — {{ $primary?->market_ar }}</div>
                    @if ($usd)
                        <div class="flex items-baseline gap-3 flex-wrap">
                            <span class="num text-5xl font-bold" data-live="usd">{{ money($usd['snapshot']->mid) }}</span>
                            <span class="num text-lg font-semibold {{ trend_class($usd['change_pct']) }}">
                                {{ $usd['change_pct'] > 0 ? '▲' : ($usd['change_pct'] < 0 ? '▼' : '•') }} {{ money(abs($usd['change'])) }} ({{ pct($usd['change_pct']) }})
                            </span>
                        </div>
                        <div class="flex items-center gap-3 mt-2 text-sm flex-wrap">
                            <span class="badge badge-{{ $usd['snapshot']->confidence }}">{{ $usd['snapshot']->confidenceLabel() }} · {{ $usd['snapshot']->sample_count }} قراءات</span>
                            <span class="text-muted">آخر تحديث {{ $usd['snapshot']->computed_at->diffForHumans() }}</span>
                            @if ($delayed)
                                <a href="{{ route('pricing') }}" class="text-xs font-semibold text-accent-600 hover:underline">⏱ متأخر 15 دقيقة — اللحظي في برو</a>
                            @else
                                <span class="text-xs font-semibold text-up">● مباشر</span>
                            @endif
                        </div>
                    @endif
                </div>
                @if ($usd)
                    <div class="grid grid-cols-2 gap-2 text-center min-w-[220px]">
                        <div class="surface-2 rounded-xl px-4 py-2.5"><div class="text-xs text-muted">شراء</div><div class="num text-xl font-bold" data-live="usd" data-field="buy">{{ money($usd['snapshot']->buy) }}</div></div>
                        <div class="surface-2 rounded-xl px-4 py-2.5"><div class="text-xs text-muted">بيع</div><div class="num text-xl font-bold" data-live="usd" data-field="sell">{{ money($usd['snapshot']->sell) }}</div></div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-4 gap-2 px-5 mt-4 text-center text-sm">
                @foreach (['open' => 'الافتتاح', 'high' => 'الأعلى', 'low' => 'الأدنى', 'close' => 'الآن'] as $k => $label)
                    <div class="border border-soft rounded-lg py-1.5"><div class="text-[11px] text-muted">{{ $label }} اليوم</div><div class="num font-semibold">{{ money($day[$k]) }}</div></div>
                @endforeach
            </div>

            <div data-price-chart data-code="usd" data-range="30d" class="card-pad">
                <div class="flex gap-1 mb-3 text-sm">
                    @foreach (\App\Services\Pricing\PriceService::RANGES as $key => $r)
                        <button data-range="{{ $key }}" class="nav-link [&.active]:bg-brand-600 [&.active]:text-white">{{ $r['label'] }}</button>
                    @endforeach
                </div>
                <div class="relative h-72">
                    <canvas></canvas>
                    <div data-chart-lock class="hidden absolute inset-0 grid place-items-center">
                        <div class="card card-pad text-center shadow-lg">
                            <div class="font-bold mb-1">💎 التاريخ الكامل للمشتركين</div>
                            <div class="text-sm text-muted mb-3">المجاني يعرض آخر 30 يوماً</div>
                            <a href="{{ route('pricing') }}" class="btn btn-accent">اشترك في برو</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-5">
            {{-- الرسمي والفجوة --}}
            @if ($cbi && $usd)
                @php($gap = ($usd['snapshot']->mid - $cbi['snapshot']->mid) / $cbi['snapshot']->mid * 100)
                <div class="card card-pad">
                    <div class="flex justify-between items-center mb-2">
                        <div class="text-sm font-semibold text-muted">🏦 السعر الرسمي (البنك المركزي)</div>
                    </div>
                    <div class="num text-2xl font-bold">{{ money($cbi['snapshot']->mid) }}</div>
                    <div class="mt-3 text-sm">الفجوة مع السوق الموازي: <b class="num text-down">{{ pct($gap, 1) }}</b></div>
                    <div class="h-2 rounded-full surface-2 mt-2 overflow-hidden"><div class="h-full bg-accent-500" style="width: {{ min(100, $gap * 5) }}%"></div></div>
                </div>
            @endif

            {{-- مؤشر الخوف --}}
            <div class="card card-pad">
                <div class="flex justify-between items-center">
                    <div class="font-bold">🌡️ مؤشر القلق على الدينار</div>
                    <span class="badge" style="background: {{ $fear['color'] }}22; color: {{ $fear['color'] }}">{{ $fear['label'] }}</span>
                </div>
                <x-gauge :value="$fear['value']" :color="$fear['color']" :label="$fear['label']" />
                <div class="text-center -mt-1 mb-3"><span class="num text-3xl font-bold" style="color: {{ $fear['color'] }}">{{ $fear['value'] }}</span><span class="text-muted text-sm"> / 100</span></div>
                <div class="space-y-2 text-xs">
                    @foreach ($fear['components'] as $c)
                        <div class="flex items-center gap-2">
                            <span class="w-28 text-muted">{{ $c['label'] }}</span>
                            <div class="flex-1 h-1.5 rounded-full surface-2 overflow-hidden"><div class="h-full rounded-full" style="width: {{ $c['value'] }}%; background: {{ $c['value'] > 60 ? 'var(--down)' : ($c['value'] < 40 ? 'var(--up)' : '#64748b') }}"></div></div>
                            <span class="num w-6 text-left">{{ $c['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- التوقعات --}}
    <section class="grid gap-5 md:grid-cols-2 mt-5">
        @php($forecastBody = null)
        <div class="card card-pad">
            <div class="flex justify-between items-center mb-3">
                <div class="font-bold">🔮 اتجاه الدولار المتوقع غداً</div>
                <span class="badge badge-pro">برو</span>
            </div>
            @if ($forecast)
                @if (auth()->user()?->hasPlan('pro'))
                    @include('pages.partials.forecast', ['forecast' => $forecast])
                @else
                    <x-locked title="التوقع الإحصائي لاتجاه السعر">
                        @include('pages.partials.forecast', ['forecast' => ['prob_up' => 50, 'direction' => 'flat', 'expected' => 145000, 'low' => 144000, 'high' => 146000, 'signal' => '—', 'accuracy' => 55, 'tested' => 40]])
                    </x-locked>
                @endif
            @else
                <div class="text-muted text-sm">لا توجد بيانات كافية بعد.</div>
            @endif
        </div>

        <div class="card card-pad">
            <div class="flex justify-between items-center mb-3">
                <div class="font-bold">👥 توقع الجمهور لإغلاق الغد</div>
                <a href="{{ route('predict') }}" class="text-sm text-brand-600 font-semibold hover:underline">شارك بتوقعك ←</a>
            </div>
            @if ($crowdForecast)
                <div class="num text-4xl font-bold">{{ money($crowdForecast) }}</div>
                <div class="text-sm text-muted mt-1">الوسيط من توقعات {{ $crowdCount }} مشاركاً في مسابقة التوقع اليومية</div>
                @if ($usd)
                    @php($diff = ($crowdForecast - $usd['snapshot']->mid) / $usd['snapshot']->mid * 100)
                    <div class="mt-3 text-sm">الجمهور يتوقع <b class="{{ trend_class($diff) }}">{{ $diff > 0 ? 'ارتفاعاً' : 'انخفاضاً' }} بنسبة {{ pct(abs($diff), 2, false) }}</b> عن السعر الحالي</div>
                @endif
            @else
                <div class="text-muted text-sm">كن أول من يتوقع سعر إغلاق الغد!</div>
            @endif
            <div class="mt-4 p-3 rounded-xl surface-2 text-sm">🎯 مسابقة يومية مجانية بالنقاط — الأدق يحصل على شارة «محلل الشهر».</div>
        </div>
    </section>

    {{-- الذهب --}}
    <section class="mt-8">
        <div class="flex justify-between items-center mb-3">
            <h2 class="section-title">⚖️ الذهب بالمثقال</h2>
            <a href="{{ route('gold') }}" class="text-sm text-brand-600 font-semibold hover:underline">هل أنصفك الصائغ؟ ←</a>
        </div>
        <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
            @foreach (['gold21' => 'عيار 21', 'gold18' => 'عيار 18', 'gold24' => 'عيار 24', 'silver' => 'الفضة (غرام)'] as $code => $label)
                @if ($quotes[$code])
                    <x-quote-card :quote="$quotes[$code]" :label="$label" :up-is-bad="false" />
                @endif
            @endforeach
        </div>
    </section>

    {{-- المحافظات ونوع الورقة --}}
    <section class="grid gap-5 lg:grid-cols-3 mt-8">
        <div class="lg:col-span-2 card overflow-hidden">
            <div class="card-pad pb-2 flex justify-between items-center">
                <h2 class="section-title">🗺️ الدولار في المحافظات</h2>
                <a href="{{ route('markets') }}" class="text-sm text-brand-600 font-semibold hover:underline">الخريطة الحرارية ←</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>السوق</th><th>شراء</th><th>بيع</th><th>الفرق عن الكفاح</th><th>الثقة</th></tr></thead>
                    <tbody>
                    @foreach ($cities as $row)
                        <tr>
                            <td class="font-semibold">{{ $row['city']->market_ar }} <span class="text-muted text-xs">{{ $row['city']->name_ar }}</span></td>
                            @if ($row['snapshot'])
                                <td class="num">{{ money($row['snapshot']->buy) }}</td>
                                <td class="num">{{ money($row['snapshot']->sell) }}</td>
                                <td class="num {{ trend_class($row['diff_pct']) }}">{{ $row['city']->is_primary ? '—' : pct($row['diff_pct']) }}</td>
                                <td><span class="badge badge-{{ $row['snapshot']->confidence }}">{{ $row['snapshot']->confidenceLabel() }}</span></td>
                            @else
                                <td colspan="4" class="text-muted">لا تتوفر بيانات</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="section-title mb-1">💵 حسب نوع الورقة</h2>
            <p class="text-xs text-muted mb-4">السوق يسعّر الدولار الأبيض (القديم) والفئات الصغيرة بأقل من فئة 100 الزرقاء.</p>
            <div class="space-y-3">
                @if ($usd)
                    <div class="flex justify-between items-center p-3 rounded-xl surface-2"><span>🔵 فئة 100 الزرقاء</span><span class="num font-bold">{{ money($usd['snapshot']->mid) }}</span></div>
                @endif
                @foreach (['white' => '⚪ الدولار الأبيض (القديم)', 'small' => '🔹 الفئات الصغيرة'] as $type => $label)
                    @if ($notes[$type] && $usd)
                        @php($d = ($notes[$type]['snapshot']->mid - $usd['snapshot']->mid) / $usd['snapshot']->mid * 100)
                        <div class="flex justify-between items-center p-3 rounded-xl surface-2">
                            <span>{{ $label }}</span>
                            <span class="text-left"><span class="num font-bold block">{{ money($notes[$type]['snapshot']->mid) }}</span><span class="num text-xs text-muted">{{ pct($d, 1) }}</span></span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    {{-- العملات الأخرى --}}
    <section class="mt-8">
        <div class="flex justify-between items-center mb-3">
            <h2 class="section-title">🌍 عملات يحتاجها العراقي</h2>
            <a href="{{ route('tools.show', 'travel') }}" class="text-sm text-brand-600 font-semibold hover:underline">ميزانية السفر ←</a>
        </div>
        <div class="grid gap-4 grid-cols-2 lg:grid-cols-5">
            @foreach (['irr' => '🇮🇷 التومان', 'try' => '🇹🇷 الليرة التركية', 'sar' => '🇸🇦 الريال السعودي', 'jod' => '🇯🇴 الدينار الأردني', 'usdt' => '₮ USDT'] as $code => $label)
                @if ($others[$code])
                    <x-quote-card :quote="$others[$code]" :label="$label" :up-is-bad="$code !== 'irr'" />
                @endif
            @endforeach
        </div>
    </section>

    {{-- الأخبار + أدوات --}}
    <section class="grid gap-5 lg:grid-cols-3 mt-8">
        <div class="lg:col-span-2 card card-pad">
            <div class="flex justify-between items-center mb-4">
                <h2 class="section-title">📡 رادار الأخبار</h2>
                <a href="{{ route('news') }}" class="text-sm text-brand-600 font-semibold hover:underline">كل الأخبار ←</a>
            </div>
            <div class="space-y-4">
                @foreach ($news as $item)
                    <div class="flex gap-3">
                        <span class="text-xl {{ $item->impactClass() }}">{{ $item->impactIcon() }}</span>
                        <div>
                            <div class="font-semibold leading-7">{{ $item->title }}</div>
                            <div class="text-xs text-muted">{{ $item->impactLabel() }} · {{ $item->published_at->diffForHumans() }} @if ($item->is_demo)· <span class="text-accent-600">خبر تجريبي</span>@endif</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="grid gap-4">
            <a href="{{ route('share') }}" class="card card-pad hover:border-brand-500 block">
                <div class="text-2xl mb-1">🖼️</div>
                <div class="font-bold">صورة أسعار اليوم للحالة</div>
                <div class="text-sm text-muted">صورة جاهزة لحالة الواتساب والستوري</div>
            </a>
            <a href="{{ route('report') }}" class="card card-pad hover:border-brand-500 block">
                <div class="text-2xl mb-1">📣</div>
                <div class="font-bold">اشتريت أو بعت الآن؟</div>
                <div class="text-sm text-muted">بلّغ عن السعر واكسب نقاطاً وثقة</div>
            </a>
            <a href="{{ route('tools') }}" class="card card-pad hover:border-brand-500 block">
                <div class="text-2xl mb-1">🧮</div>
                <div class="font-bold">7 حاسبات مالية</div>
                <div class="text-sm text-muted">الراتب الحقيقي، الزكاة، السيارة، الحوالات…</div>
            </a>
        </div>
    </section>
</x-layouts.app>
