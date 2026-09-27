<x-layouts.app title="مسابقة التوقع">
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 flex flex-col gap-5">
            <div class="card card-pad" style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-gold-400) 16%, var(--surface)), var(--surface))">
                <h1 class="text-2xl font-bold">🎯 مسابقة التوقع اليومية</h1>
                <p class="text-muted mt-2 leading-7">كم سيكون سعر إغلاق 100 دولار في بورصة الكفاح يوم <b>{{ $tomorrow->translatedFormat('l j F') }}</b>؟ مجانية بالكامل — بالنقاط فقط، بلا أي مبالغ مالية.</p>
                <div class="grid sm:grid-cols-3 gap-3 mt-4 text-center">
                    <div class="surface-2 rounded-xl p-3"><div class="text-xs text-muted">السعر الآن</div><div class="num text-xl font-bold">{{ money($current?->mid) }}</div></div>
                    <div class="surface-2 rounded-xl p-3"><div class="text-xs text-muted">توقع الجمهور ({{ $crowdCount }})</div><div class="num text-xl font-bold">{{ money($crowdMedian) }}</div></div>
                    <div class="surface-2 rounded-xl p-3"><div class="text-xs text-muted">دقة الجمهور (30 يوماً)</div><div class="num text-xl font-bold">{{ $crowdAccuracy !== null ? '±'.pct($crowdAccuracy, 2, false) : '—' }}</div></div>
                </div>
                @auth
                    <form method="POST" action="{{ route('predict') }}" class="flex gap-2 mt-5">
                        @csrf
                        <input name="predicted" type="number" class="input num text-lg" placeholder="مثال: {{ $current ? round($current->mid, -1) : 145000 }}" value="{{ $myTomorrow?->predicted ? round($myTomorrow->predicted) : '' }}" required>
                        <button class="btn btn-gold shrink-0">{{ $myTomorrow ? 'تعديل توقعي' : 'سجّل توقعي' }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-gold mt-5">سجّل الدخول للمشاركة</a>
                @endauth
                <p class="text-xs text-muted mt-3">النقاط = 100 − (نسبة الخطأ × 100). التوقع المطابق تماماً = 100 نقطة، والخطأ 1% = صفر.</p>
            </div>

            @if ($mine->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="card-pad pb-2 font-bold">توقعاتي</div>
                    <table class="table">
                        <thead><tr><th>اليوم</th><th>توقعي</th><th>الإغلاق</th><th>الخطأ</th><th>النقاط</th></tr></thead>
                        @foreach ($mine as $p)
                            <tr><td>{{ $p->target_date->translatedFormat('j F') }}</td><td class="num">{{ money($p->predicted) }}</td><td class="num">{{ money($p->actual) }}</td><td class="num">{{ $p->error_pct !== null ? pct($p->error_pct, 2, false) : 'بانتظار الإغلاق' }}</td><td class="num font-bold">{{ $p->points ?? '—' }}</td></tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-5">
            <div class="card card-pad">
                <h2 class="section-title mb-3">🏅 محللو هذا الشهر</h2>
                @foreach ($monthly as $i => $row)
                    <div class="flex items-center gap-3 py-2 {{ ! $loop->last ? 'border-b border-soft' : '' }}">
                        <span class="text-xl w-7">{{ ['🥇', '🥈', '🥉'][$i] ?? '⭐' }}</span>
                        <div class="flex-1"><div class="font-semibold">{{ $row['user']->name }}</div><div class="text-xs text-muted">{{ $row['count'] }} توقع · متوسط الخطأ <span class="num">{{ pct($row['avg_error'], 2, false) }}</span></div></div>
                        <span class="num font-bold">{{ $row['points'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="card overflow-hidden">
                <div class="card-pad pb-2"><h2 class="section-title">🏆 الترتيب العام</h2></div>
                <table class="table">
                    @foreach ($leaders as $i => $u)
                        <tr class="{{ auth()->id() === $u->id ? 'surface-2 font-bold' : '' }}"><td class="text-muted w-8">{{ $i + 1 }}</td><td>{{ $u->name }}</td><td class="num font-semibold">{{ money($u->points) }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
