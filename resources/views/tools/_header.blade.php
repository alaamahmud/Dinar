<a href="{{ route('tools') }}" class="text-sm text-muted hover:underline">→ كل الحاسبات</a>
<h1 class="text-2xl font-bold mt-2">{{ $meta['icon'] }} {{ $meta['title'] }}</h1>
<p class="text-muted text-sm mt-1 mb-5">{{ $meta['desc'] }} · سعر 100 دولار الآن: <b class="num">{{ money($usd) }}</b></p>
