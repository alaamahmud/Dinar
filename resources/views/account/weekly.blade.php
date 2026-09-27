<x-layouts.app title="التقرير الأسبوعي">
    <style>@media print { header, footer, .no-print { display: none !important; } body { background: #fff; } .card { break-inside: avoid; } }</style>
    <div class="flex justify-between items-center mb-5">
        <div>
            <h1 class="text-2xl font-bold">📄 التقرير الأسبوعي للسوق</h1>
            <div class="text-muted text-sm">{{ $from->translatedFormat('j F') }} — {{ $to->translatedFormat('j F Y') }}</div>
        </div>
        <button onclick="window.print()" class="btn btn-primary no-print">🖨️ حفظ PDF / طباعة</button>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        @foreach (['open' => 'افتتاح الأسبوع', 'close' => 'إغلاق الأسبوع', 'high' => 'الأعلى', 'low' => 'الأدنى'] as $k => $l)
            <div class="card card-pad"><div class="text-sm text-muted">{{ $l }}</div><div class="num text-2xl font-bold">{{ money($usd[$k]) }}</div></div>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-2 mt-5">
        <div class="card card-pad">
            <h2 class="section-title mb-2">الخلاصة</h2>
            @php $chg = $usd['open'] ? ($usd['close'] - $usd['open']) / $usd['open'] * 100 : 0; @endphp
            <p class="leading-8">
                {{ $chg > 0 ? 'ارتفع' : 'انخفض' }} سعر الدولار في بورصة الكفاح بنسبة <b class="num">{{ pct(abs($chg), 2, false) }}</b> خلال الأسبوع،
                ومؤشر القلق على الدينار عند <b>{{ $fear['value'] }}</b> ({{ $fear['label'] }}).
                @if ($gold['open'] && $gold['close'])
                    @php $g = ($gold['close'] - $gold['open']) / $gold['open'] * 100; @endphp
                    الذهب عيار 21 {{ $g > 0 ? 'ارتفع' : 'انخفض' }} <b class="num">{{ pct(abs($g), 2, false) }}</b>.
                @endif
                @if ($forecast) النموذج الإحصائي يعطي احتمال ارتفاع <b>{{ $forecast['prob_up'] }}%</b> لليوم التالي (دقته التاريخية {{ $forecast['accuracy'] }}%). @endif
            </p>
            @php
$chartData2 = ["labels" => $closes->keys()->map(fn ($d) => substr($d, 5))->values(), "datasets" => [["label" => "الإغلاق", "data" => $closes->values(), "color" => "brand"]]];
@endphp
            <div class="h-48 mt-3" data-static-chart="{{ json_encode($chartData2) }}"><canvas></canvas></div>
        </div>
        <div class="card card-pad">
            <h2 class="section-title mb-3">أهم الأخبار المؤثرة</h2>
            @foreach ($news as $n)
                <div class="py-2 border-b border-soft last:border-0 text-sm"><span class="{{ $n->impactClass() }}">{{ $n->impactIcon() }}</span> <b>{{ $n->title }}</b><div class="text-muted text-xs mt-1">{{ $n->summary }}</div></div>
            @endforeach
        </div>
    </div>

    <div class="card overflow-hidden mt-5">
        <table class="table">
            <thead><tr><th>السوق</th><th>السعر</th><th>الفرق عن الكفاح</th></tr></thead>
            @foreach ($cities as $row)
                @if ($row['snapshot'])<tr><td>{{ $row['city']->market_ar }}</td><td class="num">{{ money($row['snapshot']->mid) }}</td><td class="num">{{ pct($row['diff_pct']) }}</td></tr>@endif
            @endforeach
        </table>
    </div>
</x-layouts.app>
