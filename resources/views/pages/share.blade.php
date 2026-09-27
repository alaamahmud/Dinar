<x-layouts.app title="صورة أسعار اليوم">
    <div class="grid gap-6 lg:grid-cols-2 items-start">
        <div>
            <h1 class="text-2xl font-bold">🖼️ صورة أسعار اليوم</h1>
            <p class="text-muted mt-2 leading-7">صورة جاهزة بمقاس الحالة والستوري (9:16). حمّلها وانشرها على الواتساب أو إنستغرام — كل مشاركة تعرّف الناس بالمنصة.</p>
            <button data-download-card="share-card" class="btn btn-gold mt-5 text-base">⬇️ تحميل الصورة</button>
            <div class="card card-pad mt-6 text-sm text-muted leading-7">
                💡 تتحدّث الصورة تلقائياً بآخر الأسعار في كل مرة تفتح فيها هذه الصفحة.
            </div>
        </div>

        <div class="mx-auto">
            <div id="share-card" dir="rtl" style="width: 360px; height: 640px; font-family: 'IBM Plex Sans Arabic', sans-serif; background: linear-gradient(160deg, #063d33 0%, #0b7f66 55%, #0f9f7f 100%); color: #fff; border-radius: 24px; padding: 28px 24px; position: relative; overflow: hidden;">
                <div style="position:absolute; top:-80px; left:-60px; width:220px; height:220px; border-radius:50%; background: rgba(226,181,74,.18)"></div>
                <div style="display:flex; align-items:center; gap:10px; position:relative">
                    <div style="width:40px; height:40px; border-radius:12px; background:#e2b54a; color:#1b1405; display:grid; place-items:center; font-weight:800; font-size:22px">ن</div>
                    <div><div style="font-weight:800; font-size:18px">نبض الدينار</div><div style="font-size:12px; opacity:.8">{{ now()->translatedFormat('l j F Y') }}</div></div>
                </div>

                @if ($quotes['usd'])
                    <div style="margin-top:28px; background: rgba(255,255,255,.1); border-radius:18px; padding:18px; position:relative">
                        <div style="font-size:13px; opacity:.85">💵 سعر 100 دولار — بورصة الكفاح</div>
                        <div style="font-size:44px; font-weight:800; direction:ltr; text-align:right">{{ money($quotes['usd']['snapshot']->mid) }}</div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; opacity:.9">
                            <span>شراء {{ money($quotes['usd']['snapshot']->buy) }}</span><span>بيع {{ money($quotes['usd']['snapshot']->sell) }}</span>
                        </div>
                    </div>
                @endif

                <div style="margin-top:14px; display:grid; grid-template-columns:1fr 1fr; gap:10px">
                    @foreach ($cities->skip(1)->take(4) as $row)
                        @if ($row['snapshot'])
                            <div style="background: rgba(255,255,255,.08); border-radius:14px; padding:10px 12px">
                                <div style="font-size:11px; opacity:.8">{{ $row['city']->market_ar }}</div>
                                <div style="font-size:18px; font-weight:700">{{ money($row['snapshot']->mid) }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div style="margin-top:14px; background: rgba(226,181,74,.18); border:1px solid rgba(226,181,74,.5); border-radius:18px; padding:14px 16px">
                    <div style="font-size:13px; opacity:.9">🥇 الذهب (مثقال)</div>
                    <div style="display:flex; justify-content:space-between; margin-top:6px">
                        @foreach (['gold21' => 'عيار 21', 'gold24' => 'عيار 24'] as $code => $label)
                            @if ($quotes[$code])<div><div style="font-size:11px; opacity:.8">{{ $label }}</div><div style="font-size:20px; font-weight:800">{{ money($quotes[$code]['snapshot']->mid) }}</div></div>@endif
                        @endforeach
                    </div>
                </div>

                @if ($quotes['usd_cbi'])
                    <div style="margin-top:12px; font-size:13px; opacity:.9">🏦 السعر الرسمي: {{ money($quotes['usd_cbi']['snapshot']->mid) }}</div>
                @endif

                <div style="position:absolute; bottom:22px; right:24px; left:24px; display:flex; justify-content:space-between; align-items:center; font-size:12px; opacity:.85">
                    <span>أسعار استرشادية</span>
                    <span style="direction:ltr">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</span>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
