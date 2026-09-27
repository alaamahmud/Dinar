<x-layouts.app :title="$meta['title']">
    @include('tools._header')
    <div class="grid gap-5 lg:grid-cols-2">
        <form class="card card-pad grid gap-3">
            <input type="hidden" name="go" value="1">
            <div><label class="label">سعر السيارة في المزاد (دولار)</label><input class="input num" name="price" type="number" value="{{ request('price', 12000) }}" required></div>
            <div><label class="label">الشحن والنقل (دولار)</label><input class="input num" name="shipping" type="number" value="{{ request('shipping', 2200) }}"></div>
            <button class="btn btn-primary">احسب</button>
            <p class="text-xs text-muted">نسبة الجمرك ورسوم الترسيم تقديرية وقابلة للتعديل من الإدارة — تختلف حسب نوع السيارة وسنة الصنع.</p>
        </form>
        @if ($result)
            <div class="card card-pad">
                @foreach (['car' => 'سعر السيارة', 'shipping' => 'الشحن', 'customs' => 'الجمرك (تقديري)', 'registration' => 'الترسيم واللوحات'] as $k => $l)
                    <div class="flex justify-between py-2 border-b border-soft"><span>{{ $l }}</span><b class="num">{{ money($result[$k]) }}</b></div>
                @endforeach
                <div class="mt-4 text-sm text-muted">التكلفة الإجمالية</div>
                <div class="num text-4xl font-bold">{{ money($result['total']) }} <span class="text-base text-muted">د.ع</span></div>
                <div class="text-sm text-muted mt-1">≈ <span class="num">${{ money($result['total_usd']) }}</span></div>
            </div>
        @endif
    </div>
</x-layouts.app>
