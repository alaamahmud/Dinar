<x-layouts.app title="مصادر البيانات">
    <h1 class="text-2xl font-bold">🔍 مصادر البيانات ودقتها</h1>
    <p class="text-muted text-sm mt-1 mb-5 leading-7">
        نجمع السعر من عدة مصادر عامة، ونستبعد الأرقام الشاذة (أبعد من 1.5% عن الوسيط)، ثم نأخذ الوسيط الموزون.
        وزن كل مصدر يعتمد على دقته السابقة. هذا الترتيب يُحدَّث يومياً بمقارنة قراءات كل مصدر بالسعر النهائي.
    </p>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 card overflow-hidden">
            <div class="card-pad pb-2"><h2 class="section-title">🏅 ترتيب المصادر حسب الدقة</h2></div>
            <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>#</th><th>المصدر</th><th>النوع</th><th>متوسط الانحراف</th><th>القراءات</th><th>الحالة</th></tr></thead>
                <tbody>
                @foreach ($sources as $i => $source)
                    <tr>
                        <td class="text-muted">{{ $source->accuracy !== null ? $i + 1 : '—' }}</td>
                        <td class="font-semibold">{{ $source->name }}</td>
                        <td class="text-muted text-xs">{{ $source->typeLabel() }}</td>
                        <td class="num">{{ $source->accuracy !== null ? pct($source->accuracy, 3, false) : '—' }}</td>
                        <td class="num">{{ money($source->readings_count) }}</td>
                        <td>
                            @if (! $source->enabled && ! str_starts_with($source->name, 'محاكاة'))<span class="badge surface-2 text-muted">غير مفعّل</span>
                            @elseif ($source->last_error)<span class="badge badge-low" title="{{ $source->last_error }}">خطأ</span>
                            @else<span class="badge badge-high">يعمل</span>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="section-title mb-3">⭐ أوثق المبلّغين</h2>
            <p class="text-xs text-muted mb-3">ثقة المبلّغ ترتفع كلما اقتربت بلاغاته من السعر الحقيقي، فيزيد وزن بلاغاته.</p>
            <div class="space-y-2">
                @foreach ($reporters as $user)
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 grid place-items-center font-bold text-sm">{{ mb_substr($user->name, 0, 1) }}</span>
                        <div class="flex-1"><div class="font-semibold text-sm">{{ $user->name }}</div><div class="text-xs text-muted">{{ $user->reports_count }} بلاغ</div></div>
                        <div class="w-20 h-1.5 surface-2 rounded-full overflow-hidden"><div class="h-full bg-brand-500" style="width: {{ $user->trust_score * 100 }}%"></div></div>
                        <span class="num text-xs w-8">{{ round($user->trust_score * 100) }}%</span>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('report') }}" class="btn btn-primary w-full mt-4">📣 بلّغ عن سعر</a>
        </div>
    </div>
</x-layouts.app>
