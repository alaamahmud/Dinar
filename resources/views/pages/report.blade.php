<x-layouts.app title="بلّغ عن سعر">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card card-pad">
            <h1 class="text-2xl font-bold">📣 اشتريت أو بعت الآن؟</h1>
            <p class="text-muted text-sm mt-2 mb-5 leading-7">بلاغك يدخل في حساب السعر (بوزن يعتمد على دقة بلاغاتك السابقة). كل بلاغ = 5 نقاط.</p>
            @if ($current)
                <div class="surface-2 rounded-xl p-3 text-sm mb-4">السعر الحالي في الكفاح: <b class="num">{{ money($current->mid) }}</b></div>
            @endif
            <form method="POST" action="{{ route('report') }}" class="grid gap-3">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <label><input type="radio" name="side" value="buy" class="peer hidden" checked><span class="btn btn-ghost w-full peer-checked:!bg-brand-600 peer-checked:text-white">اشتريت دولار</span></label>
                    <label><input type="radio" name="side" value="sell" class="peer hidden"><span class="btn btn-ghost w-full peer-checked:!bg-brand-600 peer-checked:text-white">بعت دولار</span></label>
                </div>
                <div><label class="label">السوق / المدينة</label>
                    <select name="city_id" class="input">@foreach ($cities as $city)<option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>{{ $city->market_ar }} — {{ $city->name_ar }}</option>@endforeach</select>
                </div>
                <div><label class="label">السعر (لكل 100 دولار)</label><input name="price" type="number" class="input num text-lg" value="{{ old('price') }}" placeholder="{{ $current ? round($current->mid, -1) : 145250 }}" required></div>
                <div><label class="label">نوع الورقة</label>
                    <select name="note_type" class="input"><option value="">فئة 100 الزرقاء (العادية)</option><option value="white">الدولار الأبيض (القديم)</option><option value="small">فئات صغيرة</option></select>
                </div>
                <button class="btn btn-primary">إرسال البلاغ</button>
            </form>
        </div>

        <div class="flex flex-col gap-5">
            <div class="card card-pad">
                <div class="flex justify-between"><span class="font-bold">ثقتك</span><span class="num font-bold">{{ round(auth()->user()->trust_score * 100) }}%</span></div>
                <div class="h-2 rounded-full surface-2 mt-2 overflow-hidden"><div class="h-full bg-brand-500" style="width: {{ auth()->user()->trust_score * 100 }}%"></div></div>
                <div class="text-sm text-muted mt-3">نقاطك: <b class="num">{{ money(auth()->user()->points) }}</b></div>
            </div>
            <div class="card overflow-hidden">
                <div class="card-pad pb-2 font-bold">بلاغاتي الأخيرة</div>
                <table class="table">
                    @forelse ($myReports as $r)
                        <tr><td>{{ $r->city?->market_ar }}</td><td class="num">{{ money($r->buy ?? $r->sell) }}</td><td class="text-muted text-xs">{{ $r->buy ? 'شراء' : 'بيع' }}</td><td class="text-muted text-xs">{{ $r->recorded_at->diffForHumans() }}</td></tr>
                    @empty
                        <tr><td class="text-muted">لا توجد بلاغات بعد</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
