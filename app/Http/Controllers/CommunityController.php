<?php

namespace App\Http\Controllers;

use App\Models\BasketItem;
use App\Models\BasketPrice;
use App\Models\City;
use App\Models\Instrument;
use App\Models\Place;
use App\Models\PlaceReview;
use App\Models\Prediction;
use App\Models\PriceReading;
use App\Models\Source;
use App\Models\User;
use App\Services\Analytics\BasketIndex;
use App\Services\Pricing\PriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function __construct(private readonly PriceService $prices) {}

    /* ---------------- بلاغات الأسعار ---------------- */

    public function reportForm(): View
    {
        return view('pages.report', [
            'cities' => City::orderBy('sort')->get(),
            'current' => $this->prices->latest('usd'),
            'myReports' => PriceReading::with('city')->where('user_id', auth()->id())->latest('recorded_at')->take(10)->get(),
        ]);
    }

    public function storeReport(Request $request): RedirectResponse
    {
        [$min, $max] = config('dinar.usd_sane_range');
        $data = $request->validate([
            'city_id' => 'required|exists:cities,id',
            'side' => 'required|in:buy,sell',
            'price' => "required|numeric|min:{$min}|max:{$max}",
            'note_type' => 'nullable|in:white,small',
        ], [
            'price.min' => 'اكتب سعر 100 دولار بالدينار (مثال: 145250)',
            'price.max' => 'اكتب سعر 100 دولار بالدينار (مثال: 145250)',
        ]);

        // بلاغ بعيد جداً عن السوق يُرفض (غالباً خطأ كتابة)
        $current = $this->prices->latest('usd', (int) $data['city_id'], $data['note_type'] ?? null);
        if ($current && abs($data['price'] - $current->mid) / $current->mid > 0.05) {
            return back()->withInput()->withErrors(['price' => 'السعر بعيد جداً عن السوق الحالي ('.money($current->mid).'). تأكد من الرقم.']);
        }

        PriceReading::create([
            'instrument_id' => Instrument::byCode('usd')->id,
            'city_id' => $data['city_id'],
            'source_id' => Source::ofType('crowd')?->id,
            'user_id' => $request->user()->id,
            'buy' => $data['side'] === 'buy' ? $data['price'] : null,
            'sell' => $data['side'] === 'sell' ? $data['price'] : null,
            'note_type' => $data['note_type'] ?? null,
            'recorded_at' => now(),
        ]);
        $request->user()->increment('points', 5);

        return back()->with('status', 'شكراً! وصل بلاغك وحصلت على 5 نقاط ⭐ — كلما كانت بلاغاتك دقيقة زادت ثقتك ووزن بلاغاتك.');
    }

    /* ---------------- مسابقة التوقع ---------------- */

    public function predictions(Request $request): View
    {
        $usd = Instrument::byCode('usd');
        $tomorrow = today()->addDay();

        $leaders = User::where('points', '>', 0)->orderByDesc('points')->take(15)->get();
        $monthly = Prediction::with('user')
            ->where('target_date', '>=', today()->startOfMonth())
            ->whereNotNull('points')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($g) => ['user' => $g->first()->user, 'points' => $g->sum('points'), 'count' => $g->count(), 'avg_error' => round($g->avg('error_pct'), 3)])
            ->sortByDesc('points')
            ->take(5)
            ->values();

        $crowd = Prediction::where('instrument_id', $usd->id)->whereDate('target_date', $tomorrow)->pluck('predicted');
        $mine = $request->user() ? Prediction::where('user_id', $request->user()->id)->latest('target_date')->take(10)->get() : collect();

        // دقة «حكمة الجمهور»: متوسط توقعات الناس مقابل الإغلاق الفعلي
        $crowdAccuracy = Prediction::whereNotNull('actual')
            ->where('target_date', '>=', today()->subDays(30))
            ->get()
            ->groupBy(fn ($p) => $p->target_date->toDateString())
            ->map(fn ($g) => abs($g->median('predicted') - $g->first()->actual) / $g->first()->actual * 100)
            ->avg();

        return view('pages.predict', [
            'current' => $this->prices->latest('usd', null, null, $this->prices->cutoffFor($request->user())),
            'tomorrow' => $tomorrow,
            'leaders' => $leaders,
            'monthly' => $monthly,
            'crowdMedian' => $crowd->isNotEmpty() ? round($crowd->median(), -1) : null,
            'crowdCount' => $crowd->count(),
            'crowdAccuracy' => $crowdAccuracy !== null ? round($crowdAccuracy, 2) : null,
            'mine' => $mine,
            'myTomorrow' => $request->user() ? Prediction::where('user_id', $request->user()->id)->whereDate('target_date', $tomorrow)->first() : null,
        ]);
    }

    public function storePrediction(Request $request): RedirectResponse
    {
        [$min, $max] = config('dinar.usd_sane_range');
        $data = $request->validate(['predicted' => "required|numeric|min:{$min}|max:{$max}"]);

        Prediction::updateOrCreate(
            ['user_id' => $request->user()->id, 'instrument_id' => Instrument::byCode('usd')->id, 'target_date' => today()->addDay()],
            ['predicted' => $data['predicted']],
        );

        return back()->with('status', 'تم تسجيل توقعك لإغلاق الغد 🎯 النتيجة بعد منتصف الليل.');
    }

    /* ---------------- دليل الصرافين والصاغة ---------------- */

    public function places(Request $request): View
    {
        $type = $request->query('type');
        $cityId = $request->integer('city') ?: null;

        $places = Place::approved()->with('city')
            ->when(in_array($type, ['exchange', 'gold'], true), fn ($q) => $q->where('type', $type))
            ->when($cityId, fn ($q) => $q->where('city_id', $cityId))
            ->orderByRaw('CASE WHEN featured_until > ? THEN 0 ELSE 1 END', [now()])
            ->orderByDesc('rating_avg')
            ->get();

        return view('pages.places', [
            'places' => $places,
            'cities' => City::orderBy('sort')->get(),
            'type' => $type,
            'cityId' => $cityId,
        ]);
    }

    public function storePlace(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'type' => 'required|in:exchange,gold',
            'city_id' => 'required|exists:cities,id',
            'address' => 'nullable|string|max:200',
            'phone' => 'nullable|string|max:32',
        ]);
        Place::create([...$data, 'added_by' => $request->user()->id, 'approved' => false]);

        return back()->with('status', 'شكراً! سيظهر المحل بعد مراجعة الإدارة.');
    }

    public function review(Request $request, Place $place): RedirectResponse
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);
        PlaceReview::updateOrCreate(['place_id' => $place->id, 'user_id' => $request->user()->id], $data);
        $place->refreshRating();

        return back()->with('status', 'شكراً على تقييمك ⭐');
    }

    /* ---------------- سلة المواطن ---------------- */

    public function basket(Request $request, BasketIndex $index): View
    {
        $cityId = $request->integer('city') ?: null;

        return view('pages.basket', [
            'data' => $index->compute($cityId),
            'cities' => City::orderBy('sort')->get(),
            'items' => BasketItem::orderBy('sort')->get(),
            'cityId' => $cityId,
        ]);
    }

    public function storeBasket(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'basket_item_id' => 'required|exists:basket_items,id',
            'city_id' => 'required|exists:cities,id',
            'price' => 'required|integer|min:50|max:10000000',
        ]);
        BasketPrice::create([...$data, 'user_id' => $request->user()->id, 'reported_at' => now()]);
        $request->user()->increment('points', 2);

        return back()->with('status', 'شكراً! سعرك يساعد في بناء مؤشر التضخم الشعبي (+2 نقطة)');
    }
}
