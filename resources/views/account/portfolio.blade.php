<x-layouts.app title="محفظتي">
    <h1 class="text-2xl font-bold mb-5">💼 محفظتي</h1>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card card-pad"><div class="text-sm text-muted">القيمة الإجمالية</div><div class="num text-3xl font-bold mt-1">{{ money($total) }}</div><div class="text-xs text-muted">دينار</div></div>
        <div class="card card-pad"><div class="text-sm text-muted">تعادل بالدولار</div><div class="num text-3xl font-bold mt-1">${{ money($totalUsd) }}</div></div>
        <div class="card card-pad"><div class="text-sm text-muted">تعادل بالذهب عيار 21</div><div class="num text-3xl font-bold mt-1">{{ money($totalGold, 1) }} غ</div></div>
        <div class="card card-pad"><div class="text-sm text-muted">الربح / الخسارة</div><div class="num text-3xl font-bold mt-1 {{ $profit >= 0 ? 'text-up' : 'text-down' }}">{{ $profit >= 0 ? '+' : '' }}{{ money($profit) }}</div></div>
    </div>

    <div class="grid gap-5 lg:grid-cols-3 mt-5">
        <div class="lg:col-span-2 card overflow-hidden">
            <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>الأصل</th><th>الكمية</th><th>سعر الوحدة الآن</th><th>القيمة</th><th>الربح</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><div class="font-semibold">{{ $row['meta']['label'] }}</div><div class="text-xs text-muted">{{ $row['holding']->note }}</div></td>
                        <td class="num">{{ money($row['holding']->amount, $row['holding']->asset === 'iqd' ? 0 : 1) }} <span class="text-xs text-muted">{{ $row['meta']['unit'] }}</span></td>
                        <td class="num">{{ money($row['unit_now']) }}</td>
                        <td class="num font-semibold">{{ money($row['value']) }}</td>
                        <td class="num {{ ($row['profit'] ?? 0) >= 0 ? 'text-up' : 'text-down' }}">{{ $row['profit'] !== null ? money($row['profit']).' ('.pct($row['profit_pct'], 1).')' : '—' }}</td>
                        <td><form method="POST" action="{{ route('portfolio.destroy', $row['holding']) }}">@csrf @method('DELETE')<button class="text-muted hover:text-down">✕</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">محفظتك فارغة — أضف ما تملكه من دولار وذهب ونقد.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>
        <form method="POST" action="{{ route('portfolio') }}" class="card card-pad grid gap-3 content-start">
            @csrf
            <h2 class="section-title">➕ أضف أصلاً</h2>
            <select name="asset" class="input">@foreach ($assets as $k => $a)<option value="{{ $k }}">{{ $a['label'] }} ({{ $a['unit'] }})</option>@endforeach</select>
            <input name="amount" type="number" step="0.01" class="input" placeholder="الكمية" required>
            <input name="buy_price" type="number" step="0.01" class="input" placeholder="سعر شراء الوحدة بالدينار (اختياري)">
            <input name="bought_at" type="date" class="input">
            <input name="note" class="input" placeholder="ملاحظة (اختياري)">
            <button class="btn btn-primary">إضافة</button>
            @unless ($isPro)<p class="text-xs text-muted">المجاني: حتى 3 أصول.</p>@endunless
        </form>
    </div>

    @if ($salary && $salary['rows'])
        <div class="card card-pad mt-5">
            <div class="flex justify-between items-center mb-3">
                <h2 class="section-title">🧾 راتبك الحقيقي</h2>
                @if ($salary['loss_pct'] !== null)<span class="text-sm">خلال سنة: <b class="num {{ $salary['loss_pct'] < 0 ? 'text-down' : 'text-up' }}">{{ pct($salary['loss_pct'], 1) }}</b> بالدولار</span>@endif
            </div>
            @php
$chartData1 = ["labels" => array_column($salary["rows"], "month"), "datasets" => [["label" => "الراتب بالدولار", "data" => array_column($salary["rows"], "usd"), "color" => "brand"]]];
@endphp
            <div class="h-56" data-static-chart="{{ json_encode($chartData1) }}"><canvas></canvas></div>
        </div>
    @endif
</x-layouts.app>
