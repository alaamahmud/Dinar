<x-layouts.app title="لوحة الإدارة">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <h1 class="text-2xl font-bold">⚙️ لوحة الإدارة</h1>
        <form method="POST" action="{{ route('admin.tick') }}">@csrf<button class="btn btn-primary">🔄 جلب وحساب الأسعار الآن</button></form>
    </div>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-6">
        @foreach ([
            'users' => ['المستخدمون', '👥'], 'pro' => ['مشتركو برو', '⭐'], 'trader' => ['مشتركو تاجر', '💼'],
            'mrr' => ['الدخل الشهري (د.ع)', '💰'], 'readings_today' => ['قراءات اليوم', '📥'], 'snapshots_today' => ['أسعار محسوبة اليوم', '🧮'],
        ] as $k => [$l, $icon])
            <div class="card card-pad"><div class="text-sm text-muted">{{ $icon }} {{ $l }}</div><div class="num text-2xl font-bold mt-1">{{ money($stats[$k]) }}</div></div>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-2 mt-5">
        {{-- طلبات الاشتراك --}}
        <div class="card overflow-hidden">
            <div class="card-pad pb-2"><h2 class="section-title">💳 طلبات الاشتراك ({{ $subscriptions->count() }})</h2></div>
            <table class="table">
                @forelse ($subscriptions as $s)
                    <tr>
                        <td><div class="font-semibold">{{ $s->user->name }}</div><div class="text-xs text-muted" dir="ltr">{{ $s->user->email }}</div></td>
                        <td>{{ config('dinar.plans.'.$s->plan.'.name') }} × {{ $s->months }}</td>
                        <td class="text-xs">{{ config('dinar.payment_methods.'.$s->payment_method) }}<br><code>{{ $s->reference }}</code></td>
                        <td class="whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.subscriptions', [$s, 'approve']) }}" class="inline">@csrf<button class="btn btn-primary !py-1 !px-2 text-xs">تفعيل</button></form>
                            <form method="POST" action="{{ route('admin.subscriptions', [$s, 'reject']) }}" class="inline">@csrf<button class="btn btn-ghost !py-1 !px-2 text-xs">رفض</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="text-muted">لا توجد طلبات معلقة</td></tr>
                @endforelse
            </table>
        </div>

        {{-- إدخال سعر يدوي --}}
        <form method="POST" action="{{ route('admin.prices') }}" class="card card-pad grid grid-cols-2 gap-3 content-start">
            @csrf
            <h2 class="section-title col-span-2">✍️ إدخال سعر يدوي</h2>
            <select name="instrument_id" class="input col-span-2">@foreach ($instruments as $i)<option value="{{ $i->id }}">{{ $i->name_ar }} — {{ $i->unit_ar }}</option>@endforeach</select>
            <select name="city_id" class="input"><option value="">بدون مدينة</option>@foreach ($cities as $c)<option value="{{ $c->id }}" @selected($c->is_primary)>{{ $c->market_ar }}</option>@endforeach</select>
            <select name="note_type" class="input"><option value="">فئة 100 الزرقاء</option><option value="white">الأبيض</option><option value="small">الفئات الصغيرة</option></select>
            <input name="buy" type="number" step="0.01" class="input num" placeholder="شراء" required>
            <input name="sell" type="number" step="0.01" class="input num" placeholder="بيع" required>
            <button class="btn btn-primary col-span-2">حفظ وإعادة الحساب</button>
            <p class="text-xs text-muted col-span-2">وزن الإدخال اليدوي = 2. مفيد في البداية قبل ربط المصادر التلقائية.</p>
        </form>
    </div>

    {{-- المصادر --}}
    <div class="card overflow-hidden mt-5">
        <div class="card-pad pb-2"><h2 class="section-title">📡 مصادر البيانات</h2></div>
        <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>المصدر</th><th>النوع</th><th>آخر جلب</th><th>الدقة</th><th>الإعدادات</th></tr></thead>
            @foreach ($sources as $source)
                <tr>
                    <td class="font-semibold">{{ $source->name }} @if ($source->last_error)<div class="text-xs text-down font-normal">{{ \Illuminate\Support\Str::limit($source->last_error, 70) }}</div>@endif</td>
                    <td class="text-xs text-muted">{{ $source->typeLabel() }}</td>
                    <td class="text-xs text-muted">{{ $source->last_fetched_at?->diffForHumans() ?? '—' }}</td>
                    <td class="num">{{ $source->accuracy !== null ? pct($source->accuracy, 3, false) : '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.sources.update', $source) }}" class="flex items-center gap-2 text-xs">
                            @csrf
                            <label class="flex items-center gap-1"><input type="checkbox" name="enabled" value="1" @checked($source->enabled)> مفعّل</label>
                            <input name="weight" type="number" step="0.1" value="{{ $source->weight }}" class="input !w-16 !py-1" title="الوزن">
                            @if ($source->type === 'telegram')<input name="channel" value="{{ $source->config['channel'] ?? '' }}" class="input !w-32 !py-1" dir="ltr" placeholder="channel">@endif
                            <button class="btn btn-ghost !py-1 !px-2">حفظ</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </table>
        </div>
        <form method="POST" action="{{ route('admin.sources.store') }}" class="card-pad border-t border-soft flex flex-wrap gap-2 items-end">
            @csrf
            <div><label class="label">➕ قناة تيليجرام عامة جديدة</label><input name="name" class="input" placeholder="اسم وصفي" required></div>
            <div><label class="label">اسم القناة (بعد t.me/)</label><input name="channel" class="input" dir="ltr" placeholder="channel_name" required></div>
            <div><label class="label">المدينة الافتراضية</label><select name="default_city" class="input">@foreach ($cities as $c)<option value="{{ $c->slug }}">{{ $c->market_ar }}</option>@endforeach</select></div>
            <button class="btn btn-primary">إضافة</button>
        </form>
    </div>

    {{-- الإعدادات --}}
    <form method="POST" action="{{ route('admin.settings') }}" class="card card-pad mt-5">
        @csrf
        <h2 class="section-title mb-4">🛠️ الإعدادات</h2>
        <div class="grid gap-4 md:grid-cols-3">
            <label class="flex items-center gap-2 font-semibold"><input type="checkbox" name="demo_mode" value="1" @checked($settings['demo_mode'])> الوضع التجريبي (أسعار محاكاة)</label>
            <label class="flex items-center gap-2 font-semibold"><input type="checkbox" name="ai_enabled" value="1" @checked($settings['ai_enabled'])> تحليل الأخبار بالذكاء الاصطناعي
                <span class="badge {{ $settings['ai_key_set'] ? 'badge-high' : 'badge-low' }}">{{ $settings['ai_key_set'] ? 'المفتاح مضبوط' : 'ANTHROPIC_API_KEY غير مضبوط' }}</span></label>
            <div></div>
            <div><label class="label">سعر البنك المركزي الرسمي (للدولار الواحد)</label><input name="cbi_official_rate" class="input num" value="{{ $settings['cbi_official_rate'] }}"></div>
            <div><label class="label">فرق سوق الذهب المحلي (نسبة، مثل 0.01)</label><input name="gold_local_premium" class="input num" value="{{ $settings['gold_local_premium'] }}"></div>
            <div><label class="label">خصم الكسر (نسبة)</label><input name="scrap_discount" class="input num" value="{{ $settings['scrap_discount'] }}"></div>
            @foreach (\App\Services\Pricing\GoldCalculator::ORIGINS as $k => $l)
                <div><label class="label">أجور صياغة {{ $l }} (دينار/غرام)</label><input name="making_charges[{{ $k }}]" class="input num" value="{{ $settings['making_charges'][$k] ?? 0 }}"></div>
            @endforeach
            @foreach (['sar' => 'ريال لكل دولار', 'jod' => 'دينار أردني لكل دولار', 'try' => 'ليرة لكل دولار', 'irr_toman' => 'تومان لكل دولار'] as $k => $l)
                <div><label class="label">{{ $l }}</label><input name="fx_cross[{{ $k }}]" class="input num" value="{{ $settings['fx_cross'][$k] ?? 0 }}"></div>
            @endforeach
            <div><label class="label">نسبة جمرك السيارات</label><input name="car_customs_rate" class="input num" value="{{ $settings['car_customs_rate'] }}"></div>
            <div><label class="label">رسوم الترسيم (دينار)</label><input name="car_registration_iqd" class="input num" value="{{ $settings['car_registration_iqd'] }}"></div>
            <div class="md:col-span-3"><label class="label">خلاصات RSS للأخبار (رابط في كل سطر)</label><textarea name="news_feeds" class="input h-24" dir="ltr">{{ $settings['news_feeds'] }}</textarea></div>
        </div>
        <button class="btn btn-primary mt-4">حفظ الإعدادات</button>
    </form>

    <div class="grid gap-5 lg:grid-cols-2 mt-5">
        <div class="card overflow-hidden">
            <div class="card-pad pb-2"><h2 class="section-title">📣 آخر بلاغات المستخدمين</h2></div>
            <table class="table">
                @foreach ($reports as $r)
                    <tr>
                        <td>{{ $r->user?->name }} <span class="text-xs text-muted">({{ round(($r->user?->trust_score ?? 0) * 100) }}%)</span></td>
                        <td class="text-xs">{{ $r->city?->market_ar }}</td>
                        <td class="num">{{ money($r->buy ?? $r->sell) }}</td>
                        <td class="text-xs text-muted">{{ $r->recorded_at->diffForHumans() }}</td>
                        <td><form method="POST" action="{{ route('admin.readings.destroy', $r) }}">@csrf @method('DELETE')<button class="text-muted hover:text-down" title="حذف">✕</button></form></td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div class="flex flex-col gap-5">
            <div class="card overflow-hidden">
                <div class="card-pad pb-2"><h2 class="section-title">📍 محلات بانتظار الموافقة</h2></div>
                <table class="table">
                    @forelse ($places as $p)
                        <tr><td class="font-semibold">{{ $p->name }}</td><td class="text-xs">{{ $p->city?->market_ar }}</td>
                            <td><form method="POST" action="{{ route('admin.places.approve', $p) }}" class="flex gap-2 items-center text-xs">@csrf<label><input type="checkbox" name="featured" value="1"> مميّز</label><button class="btn btn-primary !py-1 !px-2">موافقة</button></form></td></tr>
                    @empty
                        <tr><td class="text-muted">لا يوجد</td></tr>
                    @endforelse
                </table>
            </div>
            <form method="POST" action="{{ route('admin.news.store') }}" class="card card-pad grid gap-3">
                @csrf
                <h2 class="section-title">📰 إضافة خبر يدوياً</h2>
                <input name="title" class="input" placeholder="العنوان" required>
                <textarea name="body" class="input h-20" placeholder="نص الخبر"></textarea>
                <div class="grid grid-cols-2 gap-2"><input name="source_name" class="input" placeholder="المصدر"><input name="url" class="input" dir="ltr" placeholder="https://"></div>
                <button class="btn btn-primary">إضافة وتحليل</button>
            </form>
        </div>
    </div>
</x-layouts.app>
