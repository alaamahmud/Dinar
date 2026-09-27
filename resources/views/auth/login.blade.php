<x-layouts.app title="تسجيل الدخول">
    <div class="max-w-md mx-auto card card-pad mt-6">
        <h1 class="text-2xl font-bold mb-5">تسجيل الدخول</h1>
        <form method="POST" action="{{ route('login') }}" class="grid gap-4">
            @csrf
            <div><label class="label">البريد الإلكتروني</label><input class="input" type="email" name="email" value="{{ old('email') }}" required autofocus dir="ltr"></div>
            <div><label class="label">كلمة المرور</label><input class="input" type="password" name="password" required dir="ltr"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" checked> تذكرني</label>
            <button type="submit" class="btn btn-primary">دخول</button>
        </form>
        <p class="text-sm text-muted mt-5">ليس لديك حساب؟ <a class="text-brand-600 font-semibold" href="{{ route('register') }}">سجّل مجاناً</a></p>
        @if (\App\Models\Setting::get('demo_mode'))
            <div class="mt-5 p-3 rounded-xl surface-2 text-xs leading-6 text-muted">
                حسابات تجريبية (كلمة المرور: <code>password</code>):<br>
                <span dir="ltr">admin@dinar.test</span> — المدير ·
                <span dir="ltr">pro@dinar.test</span> — برو ·
                <span dir="ltr">trader@dinar.test</span> — تاجر ·
                <span dir="ltr">free@dinar.test</span> — مجاني
            </div>
        @endif
    </div>
</x-layouts.app>
