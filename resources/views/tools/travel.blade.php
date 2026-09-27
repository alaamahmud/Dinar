<x-layouts.app :title="$meta['title']">
    @include('tools._header')
    <div class="grid gap-5 lg:grid-cols-2">
        <form class="card card-pad grid grid-cols-2 gap-3">
            <input type="hidden" name="go" value="1">
            <div class="col-span-2"><label class="label">الوجهة</label><select class="input" name="destination">@foreach ($destinations as $k => $d)<option value="{{ $k }}" @selected(request('destination', 'iran') === $k)>{{ $d['name'] }}</option>@endforeach</select></div>
            <div><label class="label">عدد الأيام</label><input class="input num" name="days" type="number" value="{{ request('days', 7) }}" required></div>
            <div><label class="label">عدد الأشخاص</label><input class="input num" name="people" type="number" value="{{ request('people', 2) }}" required></div>
            <div class="col-span-2"><label class="label">الميزانية اليومية للشخص (دولار) — اتركه فارغاً للتقدير</label><input class="input num" name="daily" type="number" value="{{ request('daily') }}"></div>
            <button class="btn btn-primary col-span-2">احسب</button>
        </form>
        @if ($result)
            <div class="card card-pad">
                <div class="text-sm text-muted">تحتاج تقريباً — {{ $result['destination']['name'] }}</div>
                <div class="num text-4xl font-bold mt-1">{{ money($result['total_iqd']) }} <span class="text-base text-muted">د.ع</span></div>
                <div class="mt-3 space-y-1.5">
                    <div>≈ <b class="num">${{ money($result['total_usd']) }}</b></div>
                    @if ($result['local_amount'])<div>≈ <b class="num">{{ money($result['local_amount']) }}</b> {{ $result['local_label'] }}</div>@endif
                </div>
                <p class="text-xs text-muted mt-4">يشمل السكن والأكل والتنقل التقريبي — بدون تذاكر الطيران.</p>
            </div>
        @endif
    </div>
</x-layouts.app>
