@props(['title' => null])
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b7f66">
    <title>{{ isset($title) ? $title.' — ' : '' }}نبض الدينار | سعر الدولار والذهب في العراق لحظياً</title>
    <meta name="description" content="سعر صرف الدولار في بورصة الكفاح والحارثية وأربيل والبصرة، وسعر الذهب عيار 21 و18 و24 بالمثقال، مع تنبيهات وتوقعات وأدوات مالية.">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <script>
        try {
            const t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased" data-refresh="30000">
@php($demo = \App\Models\Setting::get('demo_mode'))
@if ($demo)
    <div class="bg-slate-800 text-white text-center text-xs font-semibold py-1.5 px-4">
        وضع تجريبي: الأسعار المعروضة محاكاة لغرض العرض وليست أسعار السوق الحقيقية
    </div>
@endif

<header class="sticky top-0 z-40 border-b border-soft backdrop-blur" style="background: color-mix(in srgb, var(--surface) 88%, transparent)">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center gap-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2 shrink-0">
            <span class="w-9 h-9 rounded-xl bg-brand-600 text-white grid place-items-center font-bold text-lg">ن</span>
            <span class="leading-tight">
                <span class="block font-bold">نبض الدينار</span>
                <span class="block text-[11px] text-muted">الدولار والذهب لحظياً</span>
            </span>
        </a>

        <nav class="hidden lg:flex items-center gap-0.5 mr-4">
            @foreach ([
                'home' => 'الرئيسية', 'gold' => 'الذهب', 'currencies' => 'العملات', 'markets' => 'المحافظات',
                'news' => 'رادار الأخبار', 'tools' => 'الحاسبات', 'predict' => 'مسابقة التوقع', 'places' => 'الصرافون', 'basket' => 'سلة المواطن',
            ] as $route => $label)
                <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route.'*') ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="mr-auto flex items-center gap-2">
            <button data-theme-toggle class="btn btn-ghost !px-2.5 !py-2" aria-label="الوضع الليلي">🌓</button>
            @auth
                @php($unread = auth()->user()->unreadNotifications()->count())
                <a href="{{ route('account') }}" class="btn btn-ghost !py-2 relative">
                    👤 <span class="hidden sm:inline">{{ \Illuminate\Support\Str::limit(auth()->user()->name, 14) }}</span>
                    @if (auth()->user()->hasPlan('pro'))<span class="badge badge-pro">{{ auth()->user()->planName() }}</span>@endif
                    @if ($unread)<span class="absolute -top-1 -left-1 bg-red-500 text-white text-[10px] rounded-full w-5 h-5 grid place-items-center">{{ $unread }}</span>@endif
                </a>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost !py-2">دخول</a>
                <a href="{{ route('pricing') }}" class="btn btn-accent !py-2 hidden sm:inline-flex">💎 اشترك</a>
            @endauth
            <button data-menu-toggle class="btn btn-ghost !px-2.5 !py-2 lg:hidden" aria-label="القائمة">☰</button>
        </div>
    </div>
    <nav id="mobile-menu" class="hidden lg:hidden border-t border-soft px-4 py-3 grid grid-cols-3 gap-1 text-center">
        @foreach ([
            'home' => 'الرئيسية', 'gold' => 'الذهب', 'currencies' => 'العملات', 'markets' => 'المحافظات',
            'news' => 'الأخبار', 'tools' => 'الحاسبات', 'predict' => 'التوقع', 'places' => 'الصرافون', 'basket' => 'سلة المواطن',
            'report' => 'بلّغ عن سعر', 'portfolio' => 'محفظتي', 'alerts' => 'تنبيهاتي',
        ] as $route => $label)
            <a href="{{ route($route) }}" class="nav-link">{{ $label }}</a>
        @endforeach
    </nav>
</header>

<main class="max-w-7xl mx-auto px-4 py-6">
    @if (session('status'))
        <div class="card card-pad mb-5 border-brand-500 !bg-brand-50 dark:!bg-brand-900/40 text-sm font-medium">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="card card-pad mb-5 text-sm" style="border-color: var(--down)">
            @foreach ($errors->all() as $error)<div class="text-down">• {{ $error }}</div>@endforeach
        </div>
    @endif

    {{ $slot }}
</main>

<footer class="border-t border-soft mt-12">
    <div class="max-w-7xl mx-auto px-4 py-8 grid gap-6 md:grid-cols-4 text-sm">
        <div class="md:col-span-2">
            <div class="font-bold mb-2">نبض الدينار</div>
            <p class="text-muted leading-7">
                الأسعار المعروضة <b>استرشادية تقريبية</b> تُحسب من مصادر عامة متعددة وبلاغات المستخدمين، وليست عرض بيع أو شراء.
                التوقعات والمؤشرات إحصائية وليست نصيحة مالية أو استثمارية. تحقق دائماً من السعر قبل أي صفقة.
            </p>
        </div>
        <div class="space-y-1.5">
            <div class="font-semibold mb-2">المنصة</div>
            <a class="block text-muted hover:underline" href="{{ route('sources') }}">مصادر البيانات ودقتها</a>
            <a class="block text-muted hover:underline" href="{{ route('report') }}">بلّغ عن سعر</a>
            <a class="block text-muted hover:underline" href="{{ route('share') }}">صورة أسعار اليوم</a>
            <a class="block text-muted hover:underline" href="{{ route('widget') }}">ضع الأسعار في موقعك</a>
        </div>
        <div class="space-y-1.5">
            <div class="font-semibold mb-2">حسابي</div>
            <a class="block text-muted hover:underline" href="{{ route('pricing') }}">الباقات والأسعار</a>
            <a class="block text-muted hover:underline" href="{{ route('portfolio') }}">محفظتي</a>
            <a class="block text-muted hover:underline" href="{{ route('alerts') }}">تنبيهاتي</a>
            @if (auth()->user()?->is_admin)<a class="block text-muted hover:underline" href="{{ route('admin.index') }}">لوحة الإدارة</a>@endif
        </div>
    </div>
</footer>
</body>
</html>
