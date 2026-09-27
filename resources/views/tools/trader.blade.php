<x-layouts.app :title="$meta['title']">
    @include('tools._header')
    @if ($locked)
        <x-locked plan="trader" title="حاسبة التاجر">
            <div class="card card-pad h-64"></div>
        </x-locked>
    @else
        <div class="grid gap-5 lg:grid-cols-2">
            <form class="card card-pad grid grid-cols-2 gap-3">
                <input type="hidden" name="go" value="1">
                <div class="col-span-2"><label class="label">المبلغ (دولار)</label><input class="input num" name="amount" type="number" value="{{ request('amount', 50000) }}" required></div>
                <div><label class="label">سعر الشراء (لكل 100$)</label><input class="input num" name="buy" type="number" value="{{ request('buy', round($usd - 150)) }}" required></div>
                <div><label class="label">سعر البيع (لكل 100$)</label><input class="input num" name="sell" type="number" value="{{ request('sell', round($usd + 150)) }}" required></div>
                <div class="col-span-2"><label class="label">تكاليف أخرى (نقل، عمولة) بالدينار</label><input class="input num" name="costs" type="number" value="{{ request('costs', 50000) }}"></div>
                <button class="btn btn-primary col-span-2">احسب</button>
            </form>
            @if ($result)
                <div class="card card-pad">
                    @foreach (['cost' => 'كلفة الشراء', 'revenue' => 'عائد البيع'] as $k => $l)
                        <div class="flex justify-between py-2 border-b border-soft"><span>{{ $l }}</span><b class="num">{{ money($result[$k]) }}</b></div>
                    @endforeach
                    <div class="mt-4 text-sm text-muted">صافي الربح</div>
                    <div class="num text-4xl font-bold {{ $result['profit'] >= 0 ? 'text-up' : 'text-down' }}">{{ money($result['profit']) }}</div>
                    <div class="mt-3 text-sm">الهامش: <b class="num">{{ pct($result['margin_pct'], 3) }}</b> · نقطة التعادل: <b class="num">{{ money($result['break_even']) }}</b></div>
                </div>
            @endif
        </div>
    @endif
</x-layouts.app>
