<x-layouts.app title="تنبيهاتي">
    <h1 class="text-2xl font-bold">🔔 تنبيهات الأسعار</h1>
    <p class="text-muted text-sm mt-1 mb-5">يصلك إشعار في الموقع (وعلى تيليجرام إن ربطت حسابك) عند تحقق الشرط. الحد في باقتك: <b>{{ $limit ?? 'غير محدود' }}</b>.</p>
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 card overflow-hidden">
            <table class="table">
                <thead><tr><th>الأداة</th><th>الشرط</th><th>آخر إرسال</th><th></th></tr></thead>
                @forelse ($alerts as $alert)
                    <tr>
                        <td class="font-semibold">{{ $alert->instrument->name_ar }} <span class="text-xs text-muted">{{ $alert->city?->market_ar }}</span></td>
                        <td>{{ \App\Models\Alert::CONDITIONS[$alert->condition] }} @if ($alert->threshold)<b class="num">{{ money($alert->threshold) }}</b>@endif</td>
                        <td class="text-muted text-xs">{{ $alert->last_triggered_at?->diffForHumans() ?? 'لم يُرسل بعد' }}</td>
                        <td><form method="POST" action="{{ route('alerts.destroy', $alert) }}">@csrf @method('DELETE')<button class="text-muted hover:text-down">✕</button></form></td>
                    </tr>
                @empty
                    <tr><td class="text-muted">لا توجد تنبيهات.</td></tr>
                @endforelse
            </table>
        </div>
        <form method="POST" action="{{ route('alerts') }}" class="card card-pad grid gap-3 content-start">
            @csrf
            <h2 class="section-title">➕ تنبيه جديد</h2>
            <select name="instrument_id" class="input">@foreach ($instruments as $i)<option value="{{ $i->id }}">{{ $i->name_ar }}</option>@endforeach</select>
            <select name="condition" class="input">
                <option value="above">عندما يرتفع فوق</option>
                <option value="below">عندما ينخفض تحت</option>
                <option value="spike" @disabled(! $canSpike)>عند حركة مفاجئة {{ $canSpike ? '' : '(باقة التاجر)' }}</option>
            </select>
            <input name="threshold" type="number" class="input num" placeholder="{{ $current ? 'مثال: '.round($current + 500, -2) : 'السعر' }}">
            <select name="city_id" class="input"><option value="">السوق الرئيسي</option>@foreach ($cities as $c)<option value="{{ $c->id }}">{{ $c->market_ar }}</option>@endforeach</select>
            <button class="btn btn-primary">إنشاء</button>
        </form>
    </div>
</x-layouts.app>
