<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Instrument;
use App\Models\NewsItem;
use App\Models\Place;
use App\Models\PriceReading;
use App\Models\PriceSnapshot;
use App\Models\Setting;
use App\Models\Source;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Services\News\NewsAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.index', [
            'stats' => [
                'users' => User::count(),
                'pro' => User::where('plan', 'pro')->where('plan_expires_at', '>', now())->count(),
                'trader' => User::where('plan', 'trader')->where('plan_expires_at', '>', now())->count(),
                'readings_today' => PriceReading::where('recorded_at', '>=', today())->count(),
                'snapshots_today' => PriceSnapshot::where('computed_at', '>=', today())->count(),
                'mrr' => User::where('plan_expires_at', '>', now())->get()->sum(fn ($u) => config('dinar.plans.'.$u->activePlan().'.price')),
            ],
            'subscriptions' => SubscriptionRequest::with('user')->where('status', 'pending')->latest()->get(),
            'sources' => Source::orderBy('type')->get(),
            'places' => Place::with('city')->where('approved', false)->get(),
            'reports' => PriceReading::with(['user', 'city'])->whereNotNull('user_id')->latest('recorded_at')->take(15)->get(),
            'cities' => City::orderBy('sort')->get(),
            'instruments' => Instrument::orderBy('sort')->get(),
            'settings' => [
                'demo_mode' => Setting::get('demo_mode'),
                'ai_enabled' => Setting::get('ai_enabled'),
                'ai_key_set' => filled(config('dinar.ai.api_key')),
                'gold_local_premium' => Setting::get('gold_local_premium'),
                'scrap_discount' => Setting::get('scrap_discount'),
                'cbi_official_rate' => Setting::get('cbi_official_rate'),
                'making_charges' => Setting::get('making_charges'),
                'fx_cross' => Setting::get('fx_cross'),
                'news_feeds' => implode("\n", (array) Setting::get('news_feeds', [])),
                'car_customs_rate' => Setting::get('car_customs_rate'),
                'car_registration_iqd' => Setting::get('car_registration_iqd'),
            ],
            'types' => Source::TYPES,
        ]);
    }

    /**
     * إدخال سعر يدوي (مصدر «إدخال يدوي») — مفيد في البداية قبل ربط المصادر.
     */
    public function storePrice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'instrument_id' => 'required|exists:instruments,id',
            'city_id' => 'nullable|exists:cities,id',
            'buy' => 'required|numeric|min:0',
            'sell' => 'required|numeric|min:0',
            'note_type' => 'nullable|in:white,small',
        ]);

        $source = Source::firstOrCreate(['name' => 'إدخال يدوي (الإدارة)'], ['type' => 'manual', 'weight' => 2]);
        PriceReading::create([...$data, 'source_id' => $source->id, 'user_id' => null, 'recorded_at' => now()]);
        Artisan::call('dinar:tick');

        return back()->with('status', 'تم حفظ السعر وإعادة الحساب ✅');
    }

    public function tick(): RedirectResponse
    {
        Artisan::call('dinar:tick');

        return back()->with('status', trim(Artisan::output()) ?: 'تم التحديث');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gold_local_premium' => 'required|numeric|min:-0.1|max:0.2',
            'scrap_discount' => 'required|numeric|min:0|max:0.3',
            'cbi_official_rate' => 'required|numeric|min:500|max:5000',
            'making_charges' => 'required|array',
            'making_charges.*' => 'required|numeric|min:0',
            'fx_cross' => 'required|array',
            'fx_cross.*' => 'required|numeric|min:0',
            'news_feeds' => 'nullable|string|max:5000',
            'car_customs_rate' => 'required|numeric|min:0|max:2',
            'car_registration_iqd' => 'required|numeric|min:0',
        ]);

        Setting::put('demo_mode', $request->boolean('demo_mode'));
        Setting::put('ai_enabled', $request->boolean('ai_enabled'));
        foreach (['gold_local_premium', 'scrap_discount', 'cbi_official_rate', 'car_customs_rate', 'car_registration_iqd'] as $key) {
            Setting::put($key, (float) $data[$key]);
        }
        Setting::put('making_charges', array_map('floatval', $data['making_charges']));
        Setting::put('fx_cross', array_map('floatval', $data['fx_cross']));
        Setting::put('news_feeds', array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['news_feeds'] ?? ''))), fn ($url) => filter_var($url, FILTER_VALIDATE_URL))));

        return back()->with('status', 'تم حفظ الإعدادات');
    }

    public function updateSource(Request $request, Source $source): RedirectResponse
    {
        $data = $request->validate([
            'weight' => 'required|numeric|min:0|max:5',
            'channel' => 'nullable|string|max:64|regex:/^[A-Za-z0-9_]+$/',
        ]);
        $config = $source->config ?? [];
        if ($source->type === 'telegram' && filled($data['channel'] ?? null)) {
            $config['channel'] = $data['channel'];
        }
        $source->update([
            'enabled' => $request->boolean('enabled'),
            'weight' => $data['weight'],
            'config' => $config ?: null,
        ]);

        return back()->with('status', "تم تحديث المصدر: {$source->name}");
    }

    public function storeSource(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'channel' => 'required|string|max:64|regex:/^[A-Za-z0-9_]+$/',
            'default_city' => 'required|exists:cities,slug',
        ]);
        Source::create([
            'name' => $data['name'],
            'type' => 'telegram',
            'config' => ['channel' => $data['channel'], 'default_city' => $data['default_city']],
            'enabled' => true,
        ]);

        return back()->with('status', 'تمت إضافة القناة ✅');
    }

    public function handleSubscription(SubscriptionRequest $subscription, string $action): RedirectResponse
    {
        if ($action === 'approve') {
            $user = $subscription->user;
            $start = $user->activePlan() === $subscription->plan && $user->plan_expires_at?->isFuture() ? $user->plan_expires_at : now();
            $user->forceFill([
                'plan' => $subscription->plan,
                'plan_expires_at' => $start->copy()->addMonths($subscription->months),
            ])->save();
        }
        $subscription->update(['status' => $action === 'approve' ? 'approved' : 'rejected', 'handled_at' => now()]);

        return back()->with('status', $action === 'approve' ? 'تم تفعيل الاشتراك ✅' : 'تم رفض الطلب');
    }

    public function approvePlace(Request $request, Place $place): RedirectResponse
    {
        $place->update([
            'approved' => true,
            'featured_until' => $request->boolean('featured') ? now()->addMonth() : $place->featured_until,
        ]);

        return back()->with('status', 'تمت الموافقة على المحل');
    }

    public function destroyReading(PriceReading $reading): RedirectResponse
    {
        $reading->user?->decrement('points', 5);
        $reading->delete();

        return back()->with('status', 'تم حذف البلاغ');
    }

    public function storeNews(Request $request, NewsAnalyzer $analyzer): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:250',
            'body' => 'nullable|string|max:5000',
            'url' => 'nullable|url|unique:news_items,url',
            'source_name' => 'nullable|string|max:120',
        ]);
        $item = NewsItem::create([...$data, 'published_at' => now()]);
        $analyzer->analyze($item);

        return back()->with('status', 'تمت إضافة الخبر وتحليله ('.($item->analyzed_by === 'ai' ? 'بالذكاء الاصطناعي' : 'بالكلمات المفتاحية').')');
    }
}
