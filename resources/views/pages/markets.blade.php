<x-layouts.app title="الدولار في المحافظات">
    <h1 class="text-2xl font-bold">🗺️ الدولار في المحافظات</h1>
    <p class="text-muted text-sm mt-1 mb-5">مقارنة أسعار الأسواق في المدن العراقية، وفرص الفرق السعري بينها.</p>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 grid gap-3 sm:grid-cols-2">
            @php $mids = $board->filter(fn ($r) => $r['snapshot'])->map(fn ($r) => $r['snapshot']->mid); @endphp
            @php $min = $mids->min(); @endphp @php $max = $mids->max(); @endphp
            @foreach ($board as $row)
                @if ($row['snapshot'])
                    @php $heat = $max > $min ? ($row['snapshot']->mid - $min) / ($max - $min) : 0.5; @endphp
                    <div class="rounded-2xl p-4 border border-soft" style="background: color-mix(in srgb, {{ $heat > 0.5 ? 'var(--down)' : 'var(--up)' }} {{ round(abs($heat - 0.5) * 36) + 4 }}%, var(--surface))">
                        <div class="flex justify-between items-center">
                            <div class="font-bold">{{ $row['city']->market_ar }}</div>
                            <span class="num text-sm font-semibold {{ trend_class($row['diff_pct']) }}">{{ $row['city']->is_primary ? 'المرجع' : pct($row['diff_pct']) }}</span>
                        </div>
                        <div class="num text-2xl font-bold mt-2">{{ money($row['snapshot']->mid) }}</div>
                        <div class="text-xs text-muted mt-1">شراء <span class="num">{{ money($row['snapshot']->buy) }}</span> · بيع <span class="num">{{ money($row['snapshot']->sell) }}</span></div>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="flex flex-col gap-5">
            <div class="card card-pad">
                <h2 class="section-title mb-3">💼 فرصة الفرق السعري</h2>
                @if ($cheapest && $priciest)
                    @php $spread = $priciest['snapshot']->sell - $cheapest['snapshot']->buy; @endphp
                    @if ($isTrader)
                        <div class="text-sm leading-7">
                            أرخص شراء: <b>{{ $cheapest['city']->market_ar }}</b> (<span class="num">{{ money($cheapest['snapshot']->buy) }}</span>)<br>
                            أغلى بيع: <b>{{ $priciest['city']->market_ar }}</b> (<span class="num">{{ money($priciest['snapshot']->sell) }}</span>)<br>
                            الفرق لكل 100 دولار: <b class="num text-up">{{ money($spread) }}</b> دينار
                        </div>
                        <p class="text-xs text-muted mt-3">قبل النقل والعمولات والمخاطر. استخدم <a class="underline" href="{{ route('tools.show', 'trader') }}">حاسبة التاجر</a>.</p>
                    @else
                        <x-locked plan="trader" title="فرص الفرق بين المحافظات">
                            <div class="text-sm leading-7">أرخص شراء: سوق ——<br>أغلى بيع: سوق ——<br>الفرق لكل 100 دولار: 000</div>
                        </x-locked>
                    @endif
                @endif
            </div>

            <div class="card card-pad">
                <h2 class="section-title mb-1">🏦 نافذة بيع العملة</h2>
                <p class="text-xs text-muted mb-3">مبيعات البنك المركزي اليومية (مليون دولار) مقابل سعر السوق الموازي</p>
                @php
$chartData5 = [
                    "type" => "bar",
                    "legend" => true,
                    "y2" => true,
                    "labels" => $auctions->map(fn ($a) => $a->date->format("m/d"))->values(),
                    "datasets" => [
                        ["label" => "المبيعات", "data" => $auctions->pluck("sales_musd")->values(), "color" => "brand", "yAxisID" => "y", "order" => 2],
                        ["label" => "سعر الموازي", "type" => "line", "data" => $auctions->map(fn ($a) => $usdSeries[$a->date->toDateString()] ?? null)->values(), "color" => "down", "yAxisID" => "y2", "fill" => false, "order" => 1],
                    ],
                ];
@endphp
                <div class="h-56" data-static-chart="{{ json_encode($chartData5) }}"><canvas></canvas></div>
            </div>
        </div>
    </div>
</x-layouts.app>
