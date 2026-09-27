<x-layouts.app :title="$meta['title']">
    @include('tools._header')
    <div class="grid gap-5 lg:grid-cols-2">
        <form class="card card-pad grid gap-3">
            <input type="hidden" name="go" value="1">
            <div><label class="label">النقد بالدينار</label><input class="input num" name="cash" type="number" value="{{ request('cash', 5000000) }}"></div>
            <div><label class="label">الدولار (عدد الدولارات)</label><input class="input num" name="dollars" type="number" value="{{ request('dollars', 3000) }}"></div>
            <div><label class="label">ذهب عيار 21 (غرام، غير المستعمل للزينة)</label><input class="input num" name="gold" type="number" step="0.1" value="{{ request('gold', 0) }}"></div>
            <button class="btn btn-primary">احسب الزكاة</button>
        </form>
        @if ($result)
            <div class="card card-pad">
                <div class="flex justify-between py-2 border-b border-soft"><span>مجموع المال</span><b class="num">{{ money($result['wealth']) }}</b></div>
                <div class="flex justify-between py-2 border-b border-soft"><span>النصاب (85 غ ذهب)</span><b class="num">{{ money($result['nisab_gold']) }}</b></div>
                @if ($result['nisab_silver'])<div class="flex justify-between py-2 border-b border-soft"><span>النصاب (595 غ فضة)</span><b class="num">{{ money($result['nisab_silver']) }}</b></div>@endif
                @if ($result['due'])
                    <div class="mt-4 text-sm text-muted">بلغ مالك النصاب. الزكاة (2.5%) إذا حال عليه الحول:</div>
                    <div class="num text-4xl font-bold text-brand-600 mt-1">{{ money($result['zakat']) }} <span class="text-base">د.ع</span></div>
                @else
                    <div class="mt-4 font-semibold">لم يبلغ المال النصاب بالذهب.</div>
                @endif
                <p class="text-xs text-muted mt-4">للاسترشاد فقط — راجع أهل العلم في المسائل التفصيلية.</p>
            </div>
        @endif
    </div>
</x-layouts.app>
