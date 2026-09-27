<x-layouts.app :title="$meta['title']">
    @include('tools._header')
    <div class="grid gap-5 lg:grid-cols-2">
        <form class="card card-pad grid gap-3">
            <input type="hidden" name="go" value="1">
            <div><label class="label">المبلغ بالدينار</label><input class="input num" name="amount" type="number" value="{{ request('amount', 10000000) }}" required></div>
            <div><label class="label">لو حوّلته إلى</label><select class="input" name="asset">@foreach ($assets as $k => $l)<option value="{{ $k }}" @selected(request('asset', 'gold21') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div><label class="label">في تاريخ</label><input class="input" name="date" type="date" value="{{ request('date', now()->subYear()->toDateString()) }}" max="{{ now()->subDay()->toDateString() }}" required></div>
            <button class="btn btn-primary">احسب</button>
        </form>
        @if ($result)
            <div class="card card-pad">
                <div class="text-sm text-muted">قيمته اليوم</div>
                <div class="num text-4xl font-bold mt-1">{{ money($result['value_now']) }} <span class="text-base text-muted">د.ع</span></div>
                <div class="num text-lg font-semibold mt-2 {{ $result['profit'] >= 0 ? 'text-up' : 'text-down' }}">{{ $result['profit'] >= 0 ? 'ربح' : 'خسارة' }} {{ money(abs($result['profit'])) }} ({{ pct($result['profit_pct'], 1) }})</div>
                <div class="mt-4 text-sm space-y-1.5 text-muted">
                    <div>سعر الوحدة حينها: <span class="num">{{ money($result['then_price']) }}</span> — اليوم: <span class="num">{{ money($result['now_price']) }}</span></div>
                    <div>لو بقي المبلغ دينار لبقي <span class="num">{{ money(request('amount')) }}</span> — لكن قيمته الحقيقية تغيّرت.</div>
                </div>
            </div>
        @elseif (request('go'))
            <div class="card card-pad text-muted">لا توجد بيانات لهذا التاريخ.</div>
        @endif
    </div>
</x-layouts.app>
