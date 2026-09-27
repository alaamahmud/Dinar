<x-layouts.app title="الباقات">
    <div class="text-center max-w-2xl mx-auto mb-8">
        <h1 class="text-3xl font-bold">💎 اختر باقتك</h1>
        <p class="text-muted mt-2">ابدأ مجاناً. ارتقِ عندما تحتاج السعر اللحظي والتنبيهات والتحليلات.</p>
    </div>

    @php($features = [
        ['الأسعار الحالية', 'تأخير 15 دقيقة', 'لحظي', 'لحظي'],
        ['الرسم البياني', 'آخر 30 يوماً', 'كل التاريخ', 'كل التاريخ'],
        ['تنبيهات السعر', '1', '20', 'غير محدودة'],
        ['تنبيه الحركات المفاجئة', '—', '—', '✓'],
        ['المحفظة', '3 أصول', 'غير محدودة', 'غير محدودة'],
        ['رادار الأخبار', 'العناوين', 'الملخص والأثر', 'الملخص والأثر'],
        ['توقع اتجاه السعر', '—', '✓', '✓'],
        ['التقرير الأسبوعي', '—', '✓', '✓'],
        ['فرص الفرق بين المحافظات', '—', '—', '✓'],
        ['حاسبة التاجر', '—', '—', '✓'],
        ['تصدير البيانات (Excel)', '—', '—', '✓'],
        ['شاشة عرض للمحل', '—', '—', '✓'],
        ['واجهة برمجية API', '—', '—', '✓'],
    ])

    <div class="grid gap-5 md:grid-cols-3 max-w-5xl mx-auto">
        @foreach (['free', 'pro', 'trader'] as $i => $key)
            @php($plan = $plans[$key])
            <div class="card card-pad flex flex-col {{ $key === 'pro' ? 'ring-2 ring-accent-500 relative' : '' }}">
                @if ($key === 'pro')<span class="badge badge-pro absolute -top-3 right-5">الأكثر طلباً</span>@endif
                <div class="text-lg font-bold">{{ ['free' => '🆓', 'pro' => '⭐', 'trader' => '💼'][$key] }} {{ $plan['name'] }}</div>
                <div class="mt-3 mb-5"><span class="num text-4xl font-bold">{{ money($plan['price']) }}</span> <span class="text-muted text-sm">دينار / شهر</span></div>
                <ul class="space-y-2.5 text-sm flex-1">
                    @foreach ($features as $f)
                        @php($v = $f[$i + 1])
                        <li class="flex justify-between gap-2 {{ $v === '—' ? 'text-muted opacity-60' : '' }}"><span>{{ $f[0] }}</span><span class="font-semibold">{{ $v }}</span></li>
                    @endforeach
                </ul>
                @if ($key === 'free')
                    <a href="{{ route('register') }}" class="btn btn-ghost mt-6">ابدأ مجاناً</a>
                @else
                    <a href="#subscribe" onclick="document.getElementById('plan-select').value='{{ $key }}'" class="btn {{ $key === 'pro' ? 'btn-accent' : 'btn-primary' }} mt-6">اشترك في {{ $plan['name'] }}</a>
                @endif
            </div>
        @endforeach
    </div>

    <div id="subscribe" class="card card-pad max-w-2xl mx-auto mt-10">
        <h2 class="section-title">طريقة الاشتراك</h2>
        <ol class="text-sm text-muted leading-8 mt-2 list-decimal pr-5">
            <li>حوّل مبلغ الباقة عبر إحدى طرق الدفع المحلية.</li>
            <li>اكتب رقم العملية أدناه وأرسل الطلب.</li>
            <li>نفعّل اشتراكك بعد التحقق (عادة خلال ساعات).</li>
        </ol>
        @auth
            <form method="POST" action="{{ route('subscribe') }}" class="grid sm:grid-cols-2 gap-3 mt-4">
                @csrf
                <div><label class="label">الباقة</label><select id="plan-select" name="plan" class="input"><option value="pro">برو — {{ money($plans['pro']['price']) }}</option><option value="trader">تاجر — {{ money($plans['trader']['price']) }}</option></select></div>
                <div><label class="label">المدة</label><select name="months" class="input">@foreach ([1 => 'شهر', 3 => '3 أشهر', 6 => '6 أشهر', 12 => 'سنة'] as $m => $l)<option value="{{ $m }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="label">طريقة الدفع</label><select name="payment_method" class="input">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="label">رقم العملية / الوصل</label><input name="reference" class="input" required></div>
                <button class="btn btn-accent sm:col-span-2">إرسال طلب الاشتراك</button>
            </form>
        @else
            <a href="{{ route('register') }}" class="btn btn-primary mt-4">أنشئ حساباً أولاً</a>
        @endauth
    </div>
</x-layouts.app>
