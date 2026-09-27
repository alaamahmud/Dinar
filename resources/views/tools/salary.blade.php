<x-layouts.app :title="$meta['title']">
    @include('tools._header')
    <div class="grid gap-5 lg:grid-cols-3">
        <form class="card card-pad grid gap-3 content-start">
            <input type="hidden" name="go" value="1">
            <div><label class="label">راتبك الشهري بالدينار</label><input class="input num" name="salary" type="number" value="{{ request('salary', $salaryDefault ?? 1000000) }}" required></div>
            <button class="btn btn-primary">احسب</button>
            @auth<p class="text-xs text-muted">احفظ راتبك في <a class="underline" href="{{ route('account') }}">حسابك</a> ليظهر في محفظتك تلقائياً.</p>@endauth
        </form>
        @if ($result && $result['rows'])
            <div class="lg:col-span-2 card card-pad">
                <div class="flex flex-wrap gap-6 items-end mb-4">
                    <div><div class="text-sm text-muted">قيمته اليوم بالدولار</div><div class="num text-3xl font-bold">${{ money(end($result['rows'])['usd']) }}</div></div>
                    <div><div class="text-sm text-muted">بالذهب عيار 21</div><div class="num text-3xl font-bold">{{ end($result['rows'])['gold_grams'] }} غ</div></div>
                    @if ($result['loss_pct'] !== null)<div><div class="text-sm text-muted">التغير خلال سنة (بالدولار)</div><div class="num text-3xl font-bold {{ $result['loss_pct'] < 0 ? 'text-down' : 'text-up' }}">{{ pct($result['loss_pct'], 1) }}</div></div>@endif
                </div>
                @php
$chartData6 = ["legend" => true, "y2" => true, "labels" => array_column($result["rows"], "month"), "datasets" => [["label" => "بالدولار", "data" => array_column($result["rows"], "usd"), "color" => "brand", "yAxisID" => "y"], ["label" => "غرامات ذهب 21", "data" => array_column($result["rows"], "gold_grams"), "color" => "gold", "yAxisID" => "y2", "fill" => false]]];
@endphp
                <div class="h-64" data-static-chart="{{ json_encode($chartData6) }}"><canvas></canvas></div>
            </div>
        @endif
    </div>
</x-layouts.app>
