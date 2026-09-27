@props(['plan' => 'pro', 'title' => 'ميزة للمشتركين'])
<div {{ $attributes->merge(['class' => 'relative']) }}>
    <div class="locked-blur" aria-hidden="true">{{ $slot }}</div>
    <div class="absolute inset-0 grid place-items-center">
        <div class="card card-pad text-center shadow-lg max-w-xs">
            <div class="text-2xl mb-1">💎</div>
            <div class="font-bold mb-1">{{ $title }}</div>
            <div class="text-sm text-muted mb-3">متاحة في باقة {{ config("dinar.plans.{$plan}.name") }} بـ {{ money(config("dinar.plans.{$plan}.price")) }} د.ع شهرياً</div>
            <a href="{{ route('pricing') }}" class="btn btn-gold w-full">اعرف أكثر</a>
        </div>
    </div>
</div>
