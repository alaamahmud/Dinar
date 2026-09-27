<x-layouts.app title="سعر الذهب">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold">🥇 سعر الذهب في العراق</h1>
            <p class="text-muted text-sm mt-1">محسوب من سعر الأونصة العالمي وسعر الدولار في السوق الموازي. الأسعار للذهب الخام بدون أجور الصياغة.</p>
        </div>
        @if ($delayed)<a href="{{ route('pricing') }}" class="text-xs font-semibold text-accent-600">⏱ متأخر 15 دقيقة — اللحظي في برو</a>@endif
    </div>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
        @foreach (['gold21' => 'عيار 21 (مثقال)', 'gold18' => 'عيار 18 (مثقال)', 'gold24' => 'عيار 24 (مثقال)', 'gold_ounce' => 'الأونصة العالمية'] as $code => $label)
            @if ($quotes[$code])<x-quote-card :quote="$quotes[$code]" :label="$label" :up-is-bad="false" />@endif
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-5 mt-5">
        <div class="lg:col-span-3 card overflow-hidden">
            <div data-price-chart data-code="gold21" data-range="30d" data-color="#2563eb" class="card-pad">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="section-title">مثقال عيار 21</h2>
                    <div class="flex gap-1 text-sm">
                        @foreach (\App\Services\Pricing\PriceService::RANGES as $key => $r)
                            <button data-range="{{ $key }}" class="nav-link [&.active]:bg-accent-500 [&.active]:text-white">{{ $r['label'] }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="relative h-64"><canvas></canvas>
                    <div data-chart-lock class="hidden absolute inset-0 grid place-items-center"><a href="{{ route('pricing') }}" class="btn btn-accent">💎 التاريخ الكامل في برو</a></div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 card overflow-hidden">
            <div class="card-pad pb-2"><h2 class="section-title">جدول الأعيرة</h2></div>
            <table class="table">
                <thead><tr><th>العيار</th><th>الغرام</th><th>المثقال</th><th>بيع الكسر (مثقال)</th></tr></thead>
                <tbody>
                @foreach ($table as $row)
                    <tr>
                        <td class="font-semibold">{{ $row['karat'] }}</td>
                        <td class="num">{{ money($row['gram']) }}</td>
                        <td class="num font-semibold">{{ money($row['mithqal']) }}</td>
                        <td class="num text-muted">{{ money($row['scrap']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="card-pad pt-3 text-xs text-muted">المثقال = 5 غرامات. سعر «الكسر» تقديري لبيع الذهب المستعمل للصائغ.</div>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2 mt-5">
        {{-- هل أنصفك الصائغ؟ --}}
        <div class="card card-pad" id="check">
            <h2 class="section-title">⚖️ هل أنصفك الصائغ؟</h2>
            <p class="text-sm text-muted mt-1 mb-4">أدخل وزن القطعة وعيارها والسعر المعروض عليك، ونخبرك إن كان عادلاً.</p>
            <form method="GET" action="{{ route('gold') }}#check" class="grid grid-cols-2 gap-3">
                <div class="col-span-2 flex gap-2">
                    <label class="flex-1"><input type="radio" name="side" value="buy" class="peer hidden" @checked(request('side', 'buy') === 'buy')><span class="btn btn-ghost w-full peer-checked:!bg-brand-600 peer-checked:text-white">أنا أشتري</span></label>
                    <label class="flex-1"><input type="radio" name="side" value="sell" class="peer hidden" @checked(request('side') === 'sell')><span class="btn btn-ghost w-full peer-checked:!bg-brand-600 peer-checked:text-white">أنا أبيع للصائغ</span></label>
                </div>
                <div><label class="label">الوزن (غرام)</label><input class="input" name="grams" type="number" step="0.01" value="{{ request('grams', 10) }}" required></div>
                <div><label class="label">العيار</label>
                    <select class="input" name="karat">@foreach ([21, 18, 24, 22] as $k)<option value="{{ $k }}" @selected(request('karat', 21) == $k)>عيار {{ $k }}</option>@endforeach</select>
                </div>
                <div><label class="label">المنشأ (للصياغة)</label>
                    <select class="input" name="origin">@foreach ($origins as $key => $label)<option value="{{ $key }}" @selected(request('origin', 'gulf') === $key)>{{ $label }}</option>@endforeach</select>
                </div>
                <div><label class="label">السعر المعروض (دينار)</label><input class="input" name="offered" type="number" value="{{ request('offered') }}" placeholder="مثال: 1750000" required></div>
                <button class="btn btn-primary col-span-2">افحص السعر</button>
            </form>

            @if ($check)
                @php($tone = ['good' => 'var(--up)', 'warn' => '#64748b', 'bad' => 'var(--down)'][$check['tone']])
                <div class="mt-5 rounded-xl p-4" style="border: 2px solid {{ $tone }}; background: color-mix(in srgb, {{ $tone }} 8%, transparent)">
                    <div class="text-lg font-bold" style="color: {{ $tone }}">{{ $check['verdict'] }}</div>
                    <div class="grid grid-cols-2 gap-2 text-sm mt-3">
                        <div>قيمة الذهب الخام: <b class="num">{{ money($check['raw']) }}</b></div>
                        @if (request('side') !== 'sell')<div>أجور صياغة تقديرية: <b class="num">{{ money($check['making']) }}</b></div>@endif
                        <div>السعر العادل التقريبي: <b class="num">{{ money($check['fair']) }}</b></div>
                        <div>المعروض عليك: <b class="num">{{ money($check['offered']) }}</b> (<span class="num">{{ pct($check['diff_pct'], 1) }}</span>)</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-5">
            <div class="card card-pad">
                <h2 class="section-title mb-3">🛠️ متوسط أجور الصياغة (للغرام)</h2>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($origins as $key => $label)
                        <div class="surface-2 rounded-xl p-3 flex justify-between"><span>{{ $label }}</span><b class="num">{{ money($making[$key] ?? 0) }}</b></div>
                    @endforeach
                </div>
                <p class="text-xs text-muted mt-3">تقديرات متوسطة تختلف حسب المحل والتصميم — فاوض دائماً.</p>
            </div>

            @if ($nisab)
                <div class="card card-pad">
                    <h2 class="section-title mb-3">🤲 نصاب الزكاة اليوم</h2>
                    <div class="flex justify-between py-2 border-b border-soft"><span>بالذهب (85 غرام عيار 24)</span><b class="num">{{ money($nisab['gold']) }} د.ع</b></div>
                    @if ($nisab['silver'])<div class="flex justify-between py-2"><span>بالفضة (595 غرام)</span><b class="num">{{ money($nisab['silver']) }} د.ع</b></div>@endif
                    <a href="{{ route('tools.show', 'zakat') }}" class="btn btn-ghost w-full mt-3">احسب زكاتك ←</a>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                @foreach (['silver' => 'الفضة (غرام)', 'silver_ounce' => 'أونصة الفضة'] as $code => $label)
                    @if ($quotes[$code])<x-quote-card :quote="$quotes[$code]" :label="$label" :up-is-bad="false" />@endif
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
