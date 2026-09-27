<x-layouts.app title="ضع الأسعار في موقعك">
    <h1 class="text-2xl font-bold">🧩 ضع أسعار الدولار والذهب في موقعك</h1>
    <p class="text-muted mt-2 mb-6">مجاناً لأي موقع أو مدونة. انسخ الكود والصقه في صفحتك.</p>
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card card-pad">
            <label class="label">الكود</label>
            <textarea id="embed-code" class="input font-mono text-xs h-28" dir="ltr" readonly><iframe src="{{ route('embed') }}" width="320" height="190" style="border:0;border-radius:12px" loading="lazy" title="نبض الدينار"></iframe></textarea>
            <button data-copy="embed-code" class="btn btn-primary mt-3">نسخ الكود</button>
            <p class="text-xs text-muted mt-3">للوضع الداكن أضف <code dir="ltr">?theme=dark</code> إلى الرابط.</p>
        </div>
        <div class="card card-pad grid place-items-center gap-4">
            <iframe src="{{ route('embed') }}" width="320" height="190" style="border:0;border-radius:12px" title="معاينة"></iframe>
            <iframe src="{{ route('embed', ['theme' => 'dark']) }}" width="320" height="190" style="border:0;border-radius:12px" title="معاينة داكنة"></iframe>
        </div>
    </div>
</x-layouts.app>
