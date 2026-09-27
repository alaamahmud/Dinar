<x-layouts.app title="دليل الصرافين والصاغة">
    <div class="flex flex-wrap justify-between items-end gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold">📍 دليل الصرافين ومحلات الذهب</h1>
            <p class="text-muted text-sm mt-1">يضيفها ويقيّمها المستخدمون — مثل تقييمات الخرائط، لكن للصرافة والذهب.</p>
        </div>
        <form class="flex gap-2 text-sm">
            <select name="type" class="input !w-auto" onchange="this.form.submit()"><option value="">الكل</option><option value="exchange" @selected($type === 'exchange')>💱 صيرفة</option><option value="gold" @selected($type === 'gold')>🥇 ذهب</option></select>
            <select name="city" class="input !w-auto" onchange="this.form.submit()"><option value="">كل المدن</option>@foreach ($cities as $c)<option value="{{ $c->id }}" @selected($cityId === $c->id)>{{ $c->market_ar }}</option>@endforeach</select>
        </form>
    </div>

    <div class="grid gap-5 lg:grid-cols-5">
        <div class="lg:col-span-3 card overflow-hidden">
            @php
$chartData4 = $places->map(fn ($p) => ["name" => $p->name, "lat" => $p->lat, "lng" => $p->lng, "type" => $p->type, "address" => $p->address, "rating" => $p->rating_avg]); @endphp
            <div class="h-[440px] z-0" data-map="{{ json_encode($chartData4) }}"></div>
        </div>
        <div class="lg:col-span-2 space-y-3 max-h-[440px] overflow-y-auto">
            @foreach ($places as $place)
                <div class="card card-pad !py-3 {{ $place->isFeatured() ? 'ring-1 ring-accent-500' : '' }}">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <div class="font-bold">{{ $place->type === 'gold' ? '🥇' : '💱' }} {{ $place->name }} @if ($place->isFeatured())<span class="badge badge-pro">مميّز</span>@endif</div>
                            <div class="text-xs text-muted mt-0.5">{{ $place->city?->market_ar }} · {{ $place->address }}</div>
                        </div>
                        <div class="text-left shrink-0"><div class="font-bold text-accent-600">★ <span class="num">{{ number_format($place->rating_avg, 1) }}</span></div><div class="text-[11px] text-muted">{{ $place->rating_count }} تقييم</div></div>
                    </div>
                    @auth
                        <form method="POST" action="{{ route('places.review', $place) }}" class="flex gap-1 mt-2 items-center text-sm">
                            @csrf
                            <select name="rating" class="input !w-20 !py-1">@for ($s = 5; $s >= 1; $s--)<option value="{{ $s }}">{{ $s }} ★</option>@endfor</select>
                            <input name="comment" class="input !py-1" placeholder="تعليق قصير (اختياري)">
                            <button class="btn btn-ghost !py-1">قيّم</button>
                        </form>
                    @endauth
                </div>
            @endforeach
        </div>
    </div>

    @auth
        <div class="card card-pad mt-6">
            <h2 class="section-title mb-3">➕ أضف محلاً</h2>
            <form method="POST" action="{{ route('places.store') }}" class="grid sm:grid-cols-5 gap-3">
                @csrf
                <input name="name" class="input sm:col-span-2" placeholder="اسم المحل" required>
                <select name="type" class="input"><option value="exchange">صيرفة</option><option value="gold">محل ذهب</option></select>
                <select name="city_id" class="input">@foreach ($cities as $c)<option value="{{ $c->id }}">{{ $c->market_ar }}</option>@endforeach</select>
                <input name="address" class="input" placeholder="العنوان">
                <button class="btn btn-primary sm:col-span-5">إرسال للمراجعة</button>
            </form>
            <p class="text-xs text-muted mt-3">أصحاب المحلات يمكنهم طلب «الظهور المميز» أعلى القائمة — تواصل معنا.</p>
        </div>
    @endauth
</x-layouts.app>
