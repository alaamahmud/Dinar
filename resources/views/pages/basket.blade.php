<x-layouts.app title="سلة المواطن">
    <div class="flex flex-wrap justify-between items-end gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold">🛒 سلة المواطن — مؤشر التضخم الشعبي</h1>
            <p class="text-muted text-sm mt-1">يبنيه الناس من أسعار ما يشترونه فعلاً. الأساس = 100 في أول شهر.</p>
        </div>
        <form><select name="city" class="input !w-auto" onchange="this.form.submit()"><option value="">كل العراق</option>@foreach ($cities as $c)<option value="{{ $c->id }}" @selected($cityId === $c->id)>{{ $c->name_ar }} — {{ $c->market_ar }}</option>@endforeach</select></form>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 card card-pad">
            <div class="flex justify-between items-center mb-3">
                <h2 class="section-title">المؤشر خلال 12 شهراً</h2>
                @if ($data['change_pct'] !== null)<span class="num text-2xl font-bold {{ trend_class($data['change_pct']) }}">{{ pct($data['change_pct'], 1) }}</span>@endif
            </div>
            @php
$chartData3 = ["labels" => $data["months"], "datasets" => [["label" => "المؤشر", "data" => $data["index"], "color" => "gold"]]];
@endphp
            <div class="h-72" data-static-chart="{{ json_encode($chartData3) }}"><canvas></canvas></div>
        </div>

        <div class="card card-pad">
            <h2 class="section-title mb-3">➕ بلّغ عن سعر سلعة</h2>
            @auth
                <form method="POST" action="{{ route('basket') }}" class="grid gap-3">
                    @csrf
                    <select name="basket_item_id" class="input">@foreach ($items as $item)<option value="{{ $item->id }}">{{ $item->name_ar }} ({{ $item->unit_ar }})</option>@endforeach</select>
                    <select name="city_id" class="input">@foreach ($cities as $c)<option value="{{ $c->id }}">{{ $c->name_ar }} — {{ $c->market_ar }}</option>@endforeach</select>
                    <input name="price" type="number" class="input" placeholder="السعر بالدينار" required>
                    <button class="btn btn-primary">إرسال (+2 نقطة)</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary w-full">سجّل الدخول للمشاركة</a>
            @endauth
        </div>
    </div>

    <div class="card overflow-hidden mt-5">
        <table class="table">
            <thead><tr><th>السلعة</th><th>الوحدة</th><th>السعر الحالي (الوسيط)</th><th>قبل سنة</th><th>التغير</th></tr></thead>
            <tbody>
            @foreach ($data['items'] as $row)
                <tr>
                    <td class="font-semibold">{{ $row['item']->name_ar }}</td>
                    <td class="text-muted">{{ $row['item']->unit_ar }}</td>
                    <td class="num font-semibold">{{ money($row['price']) }}</td>
                    <td class="num text-muted">{{ money($row['base']) }}</td>
                    <td class="num {{ trend_class($row['change_pct']) }}">{{ pct($row['change_pct'], 1) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
