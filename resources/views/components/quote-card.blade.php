@props(['quote', 'label' => null, 'big' => false, 'upIsBad' => true, 'sub' => null])
@php($snap = $quote['snapshot'] ?? null)
<div {{ $attributes->merge(['class' => 'card card-pad flex flex-col gap-2']) }}>
    <div class="flex items-center justify-between gap-2">
        <div class="text-sm font-semibold text-muted">{{ $label ?? $quote['instrument']->name_ar ?? '' }}</div>
        @if ($snap)
            <span class="badge badge-{{ $snap->confidence }}">{{ $snap->confidenceLabel() }}</span>
        @endif
    </div>
    @if ($snap)
        <div class="flex items-baseline gap-2 flex-wrap">
            <span class="num font-bold {{ $big ? 'text-4xl' : 'text-2xl' }}" data-live="{{ $quote['instrument']->code }}">{{ price($snap->mid, $quote['instrument']) }}</span>
            <span class="num text-sm font-semibold {{ trend_class($quote['change_pct'], $upIsBad) }}">
                {{ $quote['change_pct'] > 0 ? '▲' : ($quote['change_pct'] < 0 ? '▼' : '•') }} {{ pct($quote['change_pct']) }}
            </span>
        </div>
        <div class="text-xs text-muted">{{ $sub ?? $quote['instrument']->unit_ar }}</div>
        @if ($snap->buy != $snap->sell)
            <div class="grid grid-cols-2 gap-2 text-sm mt-1">
                <div class="surface-2 rounded-lg px-3 py-2"><span class="text-muted text-xs block">شراء</span><span class="num font-semibold" data-live="{{ $quote['instrument']->code }}" data-field="buy">{{ price($snap->buy, $quote['instrument']) }}</span></div>
                <div class="surface-2 rounded-lg px-3 py-2"><span class="text-muted text-xs block">بيع</span><span class="num font-semibold" data-live="{{ $quote['instrument']->code }}" data-field="sell">{{ price($snap->sell, $quote['instrument']) }}</span></div>
            </div>
        @endif
        <div class="text-[11px] text-muted mt-auto">آخر تحديث {{ $snap->computed_at->diffForHumans() }}</div>
    @else
        <div class="text-muted text-sm py-4">لا تتوفر بيانات بعد</div>
    @endif
</div>
