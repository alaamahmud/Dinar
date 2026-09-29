<x-layouts.app :title="$meta['title']">
    @include('tools._header')
    <div class="grid gap-5 lg:grid-cols-3">
        <form class="card card-pad grid gap-3 content-start">
            <input type="hidden" name="go" value="1">
            <div><label class="label">المبلغ المرسل من الخارج (دولار)</label><input class="input num" name="amount" type="number" value="{{ request('amount', 1000) }}" required></div>
            <button class="btn btn-primary">قارن</button>
        </form>
        @if ($result)
            <div class="lg:col-span-2 space-y-3">
                @foreach ($result as $i => $m)
                    <div class="card card-pad flex justify-between items-center gap-4 {{ $i === 0 ? 'ring-2 ring-brand-500' : '' }}">
                        <div>
                            <div class="font-bold">{{ $i === 0 ? '🎯 ' : '' }}{{ $m['name'] }}</div>
                            <div class="text-xs text-muted mt-1">{{ $m['note'] }} · العمولة ≈ <span class="num">${{ money($m['fee_usd'], 1) }}</span></div>
                        </div>
                        <div class="text-left shrink-0"><div class="num text-2xl font-bold">{{ money($m['received']) }}</div><div class="text-xs text-muted">دينار تستلمه</div></div>
                    </div>
                @endforeach
                <p class="text-xs text-muted">العمولات تقديرية وتختلف بين الشركات والمكاتب.</p>
            </div>
        @endif
    </div>
</x-layouts.app>
