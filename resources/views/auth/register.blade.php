<x-layouts.app title="حساب جديد">
    <div class="max-w-md mx-auto card card-pad mt-6">
        <h1 class="text-2xl font-bold mb-1">حساب جديد</h1>
        <p class="text-sm text-muted mb-5">مجاني — للتنبيهات والمحفظة ومسابقة التوقع وبلاغات الأسعار.</p>
        <form method="POST" action="{{ route('register') }}" class="grid gap-4">
            @csrf
            <div><label class="label">الاسم</label><input class="input" name="name" value="{{ old('name') }}" required></div>
            <div><label class="label">البريد الإلكتروني</label><input class="input" type="email" name="email" value="{{ old('email') }}" required dir="ltr"></div>
            <div><label class="label">كلمة المرور (8 أحرف على الأقل)</label><input class="input" type="password" name="password" required dir="ltr"></div>
            <div><label class="label">تأكيد كلمة المرور</label><input class="input" type="password" name="password_confirmation" required dir="ltr"></div>
            <button type="submit" class="btn btn-primary">إنشاء الحساب</button>
        </form>
    </div>
</x-layouts.app>
