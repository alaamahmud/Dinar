<x-layouts.app title="الحاسبات المالية">
    <h1 class="text-2xl font-bold">🧮 الحاسبات المالية</h1>
    <p class="text-muted text-sm mt-1 mb-6">كل الحسابات بأسعار اليوم الفعلية من المنصة.</p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($tools as $key => $tool)
            <a href="{{ route('tools.show', $key) }}" class="card card-pad hover:border-brand-500 block">
                <div class="flex justify-between"><span class="text-3xl">{{ $tool['icon'] }}</span>@if ($tool['plan'])<span class="badge badge-pro">{{ config('dinar.plans.'.$tool['plan'].'.name') }}</span>@endif</div>
                <div class="font-bold mt-2">{{ $tool['title'] }}</div>
                <div class="text-sm text-muted mt-1 leading-6">{{ $tool['desc'] }}</div>
            </a>
        @endforeach
    </div>
</x-layouts.app>
