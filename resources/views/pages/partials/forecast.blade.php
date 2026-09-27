<div class="flex items-center gap-5">
    <div class="text-center">
        <div class="num text-4xl font-bold {{ $forecast['prob_up'] >= 50 ? 'text-down' : 'text-up' }}">{{ $forecast['prob_up'] }}%</div>
        <div class="text-xs text-muted">احتمال الارتفاع</div>
    </div>
    <div class="flex-1 text-sm space-y-1.5">
        <div>السعر المتوقع: <b class="num">{{ money($forecast['expected']) }}</b></div>
        <div>المدى المرجح: <span class="num">{{ money($forecast['low']) }} – {{ money($forecast['high']) }}</span></div>
        <div class="text-muted">{{ $forecast['signal'] }}</div>
    </div>
</div>
<div class="mt-4 p-3 rounded-xl surface-2 text-xs text-muted leading-6">
    📊 دقة النموذج في آخر {{ $forecast['tested'] }} يوماً: <b class="num">{{ $forecast['accuracy'] ?? '—' }}%</b> في تحديد الاتجاه.
    نعرض الدقة بشفافية — هذا نموذج إحصائي وليس نصيحة مالية.
</div>
