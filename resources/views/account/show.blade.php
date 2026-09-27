<x-layouts.app title="حسابي">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <h1 class="text-2xl font-bold">👤 حسابي</h1>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost">تسجيل الخروج</button></form>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="card card-pad">
            <div class="text-sm text-muted">باقتك الحالية</div>
            <div class="text-2xl font-bold mt-1">{{ $user->planName() }} @if ($user->hasPlan('pro'))<span class="badge badge-pro">💎</span>@endif</div>
            @if ($user->plan_expires_at && $user->hasPlan('pro'))<div class="text-sm text-muted mt-1">تنتهي {{ $user->plan_expires_at->translatedFormat('j F Y') }} ({{ $user->plan_expires_at->diffForHumans() }})</div>@endif
            @if ($pending)<div class="mt-3 badge badge-medium">طلب اشتراك {{ config('dinar.plans.'.$pending->plan.'.name') }} قيد المراجعة</div>@endif
            <a href="{{ route('pricing') }}" class="btn btn-gold w-full mt-4">{{ $user->hasPlan('pro') ? 'تجديد / ترقية' : 'اشترك الآن' }}</a>
            <div class="grid grid-cols-2 gap-2 mt-4 text-center">
                <div class="surface-2 rounded-xl p-2"><div class="text-xs text-muted">النقاط</div><div class="num font-bold">{{ money($user->points) }}</div></div>
                <div class="surface-2 rounded-xl p-2"><div class="text-xs text-muted">الثقة</div><div class="num font-bold">{{ round($user->trust_score * 100) }}%</div></div>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="section-title mb-3">بياناتي</h2>
            <form method="POST" action="{{ route('account') }}" class="grid gap-3">
                @csrf
                <div><label class="label">الاسم</label><input class="input" name="name" value="{{ $user->name }}"></div>
                <div><label class="label">راتبي الشهري (لحاسبة «راتبك الحقيقي»)</label><input class="input num" name="monthly_salary" type="number" value="{{ $user->monthly_salary }}"></div>
                <button class="btn btn-primary">حفظ</button>
            </form>
            <div class="grid grid-cols-2 gap-2 mt-4">
                <a href="{{ route('portfolio') }}" class="btn btn-ghost">💼 محفظتي</a>
                <a href="{{ route('alerts') }}" class="btn btn-ghost">🔔 تنبيهاتي</a>
                @if ($user->hasPlan('pro'))<a href="{{ route('weekly') }}" class="btn btn-ghost col-span-2">📄 التقرير الأسبوعي</a>@endif
            </div>
        </div>

        <div class="card card-pad">
            <div class="flex justify-between items-center mb-3">
                <h2 class="section-title">🔔 الإشعارات</h2>
                @if ($user->unreadNotifications->isNotEmpty())<form method="POST" action="{{ route('notifications.read') }}">@csrf<button class="text-xs text-brand-600">تعليم كمقروء</button></form>@endif
            </div>
            <div class="space-y-2">
                @forelse ($notifications as $n)
                    <div class="text-sm p-2.5 rounded-lg {{ $n->read_at ? '' : 'surface-2 font-semibold' }}">{{ $n->data['message'] ?? '' }}<div class="text-[11px] text-muted font-normal">{{ $n->created_at->diffForHumans() }}</div></div>
                @empty
                    <div class="text-sm text-muted">لا توجد إشعارات</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2 mt-5">
        <div class="card card-pad">
            <h2 class="section-title mb-2">📲 ربط تيليجرام</h2>
            @if ($user->telegram_chat_id)
                <div class="text-up font-semibold">✅ حسابك مربوط — ستصلك التنبيهات على تيليجرام.</div>
            @else
                <p class="text-sm text-muted leading-7">أرسل هذه الرسالة إلى بوت المنصة لتصلك التنبيهات فوراً:</p>
                <div class="surface-2 rounded-xl p-3 mt-2 text-center"><code class="text-xl font-bold">ربط {{ $linkCode }}</code></div>
                @unless ($botEnabled)<p class="text-xs text-muted mt-2">(البوت غير مفعّل بعد — يحتاج TELEGRAM_BOT_TOKEN)</p>@endunless
            @endif
        </div>

        <div class="card card-pad">
            <h2 class="section-title mb-2">💼 أدوات التاجر</h2>
            @if ($apiToken)
                <label class="label">رابط شاشة المحل (افتحه على تلفاز المحل)</label>
                <div class="flex gap-2"><input id="display-url" class="input text-xs" dir="ltr" readonly value="{{ route('display', $displayToken) }}"><button data-copy="display-url" class="btn btn-ghost shrink-0">نسخ</button></div>
                <label class="label mt-3">رمز الواجهة البرمجية API</label>
                <div class="flex gap-2"><input id="api-token" class="input text-xs font-mono" dir="ltr" readonly value="{{ $apiToken }}"><button data-copy="api-token" class="btn btn-ghost shrink-0">نسخ</button></div>
                <p class="text-xs text-muted mt-2" dir="ltr">GET {{ route('api.v1.prices') }}?token=…</p>
                <a href="{{ route('export') }}" class="btn btn-ghost w-full mt-3">⬇️ تصدير تاريخ الأسعار (Excel/CSV)</a>
            @else
                <x-locked plan="trader" title="أدوات التاجر">
                    <div class="h-40 surface-2 rounded-xl"></div>
                </x-locked>
            @endif
        </div>
    </div>
</x-layouts.app>
