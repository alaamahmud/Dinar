<x-layouts.app title="رادار الأخبار">
    <div class="flex flex-wrap justify-between items-end gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold">📡 رادار الأخبار</h1>
            <p class="text-muted text-sm mt-1">كل خبر اقتصادي ملخّص في سطر، مع أثره المحتمل على سعر الدولار.</p>
        </div>
        <div class="flex gap-1 text-sm">
            <a href="{{ route('news') }}" class="nav-link {{ ! $impact ? 'active' : '' }}">الكل</a>
            <a href="{{ route('news', ['impact' => 'up']) }}" class="nav-link {{ $impact === 'up' ? 'active' : '' }}">▲ قد يرفع</a>
            <a href="{{ route('news', ['impact' => 'down']) }}" class="nav-link {{ $impact === 'down' ? 'active' : '' }}">▼ قد يخفض</a>
            <a href="{{ route('news', ['impact' => 'neutral']) }}" class="nav-link {{ $impact === 'neutral' ? 'active' : '' }}">● محدود</a>
        </div>
    </div>

    <div class="card card-pad mb-5 text-sm flex items-center gap-2">
        {{ $aiEnabled ? '🤖 التحليل يتم بالذكاء الاصطناعي.' : '🔎 التحليل حالياً بالكلمات المفتاحية (مجاني). يمكن تفعيل تحليل الذكاء الاصطناعي من لوحة الإدارة.' }}
        @unless ($isPro)<span class="text-muted">— الملخص والأثر التفصيلي للمشتركين.</span>@endunless
    </div>

    <div class="space-y-3">
        @forelse ($items as $item)
            <article class="card card-pad flex gap-4">
                <div class="text-2xl {{ $item->impactClass() }}">{{ $item->impactIcon() }}</div>
                <div class="flex-1">
                    <h2 class="font-bold leading-7">{{ $item->title }}</h2>
                    @if ($isPro)
                        <p class="text-sm mt-1 leading-7">{{ $item->summary }}</p>
                    @else
                        <p class="text-sm mt-1 leading-7 locked-blur">{{ $item->summary }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-2 mt-2 text-xs text-muted">
                        <span class="badge {{ $item->impact === 'up' ? 'badge-low' : ($item->impact === 'down' ? 'badge-high' : 'badge-medium') }}">{{ $item->impactLabel() }}</span>
                        @if ($isPro && $item->impact !== 'neutral')<span>القوة: {{ str_repeat('●', $item->impact_strength) }}{{ str_repeat('○', 3 - $item->impact_strength) }}</span>@endif
                        <span>{{ $item->source_name }}</span>
                        <span>{{ $item->published_at->diffForHumans() }}</span>
                        <span>{{ $item->analyzed_by === 'ai' ? '🤖 ذكاء اصطناعي' : '🔎 كلمات مفتاحية' }}</span>
                        @if ($item->is_demo)<span class="text-gold-600">خبر تجريبي</span>@endif
                    </div>
                </div>
            </article>
        @empty
            <div class="card card-pad text-muted">لا توجد أخبار بعد.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $items->links() }}</div>
</x-layouts.app>
