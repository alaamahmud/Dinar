<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\City;
use App\Models\Holding;
use App\Models\Instrument;
use App\Models\NewsItem;
use App\Models\SubscriptionRequest;
use App\Services\Analytics\FearIndex;
use App\Services\Analytics\Forecaster;
use App\Services\Calculators;
use App\Services\Pricing\PriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public const HOLDING_ASSETS = [
        'usd' => ['label' => 'دولار', 'unit' => 'دولار'],
        'gold24' => ['label' => 'ذهب عيار 24', 'unit' => 'غرام'],
        'gold21' => ['label' => 'ذهب عيار 21', 'unit' => 'غرام'],
        'gold18' => ['label' => 'ذهب عيار 18', 'unit' => 'غرام'],
        'iqd' => ['label' => 'دينار نقداً', 'unit' => 'دينار'],
    ];

    public function __construct(private readonly PriceService $prices) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'notifications' => $user->notifications()->take(10)->get(),
            'pending' => SubscriptionRequest::where('user_id', $user->id)->where('status', 'pending')->first(),
            'linkCode' => $user->telegram_chat_id ? null : $user->ensureTelegramLinkCode(),
            'apiToken' => $user->hasPlan('trader') ? $user->ensureApiToken() : null,
            'displayToken' => $user->hasPlan('trader') ? $user->ensureDisplayToken() : null,
            'botEnabled' => filled(config('dinar.telegram.bot_token')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'monthly_salary' => 'nullable|integer|min:0|max:100000000',
        ]);
        $request->user()->update($data);

        return back()->with('status', 'تم حفظ التغييرات');
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => 'required|in:pro,trader',
            'months' => 'required|integer|in:1,3,6,12',
            'payment_method' => 'required|in:'.implode(',', array_keys(config('dinar.payment_methods'))),
            'reference' => 'required|string|max:80',
        ]);
        SubscriptionRequest::create([...$data, 'user_id' => $request->user()->id]);

        return redirect()->route('account')->with('status', 'وصل طلب اشتراكك ✅ سيتم تفعيله بعد التحقق من الدفع (عادة خلال ساعات).');
    }

    /* ---------------- المحفظة ---------------- */

    public function portfolio(Request $request): View
    {
        $user = $request->user();
        $usd = $this->prices->latest('usd')?->mid ?? 0;
        $gram = fn ($code) => ($this->prices->latest($code)?->mid ?? 0) / 5;

        $rows = $user->holdings()->latest()->get()->map(function (Holding $h) use ($usd, $gram) {
            [$value, $unitNow] = match ($h->asset) {
                'usd' => [$h->amount * $usd / 100, $usd / 100],
                'iqd' => [$h->amount, 1],
                default => [$h->amount * $gram($h->asset), $gram($h->asset)],
            };
            $cost = $h->buy_price ? $h->amount * $h->buy_price : null;

            return [
                'holding' => $h,
                'meta' => self::HOLDING_ASSETS[$h->asset],
                'unit_now' => $unitNow,
                'value' => $value,
                'cost' => $cost,
                'profit' => $cost !== null ? $value - $cost : null,
                'profit_pct' => $cost ? ($value - $cost) / $cost * 100 : null,
            ];
        });

        $total = $rows->sum('value');

        return view('account.portfolio', [
            'rows' => $rows,
            'total' => $total,
            'totalUsd' => $usd > 0 ? $total / $usd * 100 : 0,
            'totalGold' => $gram('gold21') > 0 ? $total / $gram('gold21') : 0,
            'profit' => $rows->sum('profit'),
            'assets' => self::HOLDING_ASSETS,
            'isPro' => $user->hasPlan('pro'),
            'salary' => $user->monthly_salary ? app(Calculators::class)->salaryValue((float) $user->monthly_salary, 12) : null,
        ]);
    }

    public function storeHolding(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasPlan('pro') && $user->holdings()->count() >= 3) {
            return back()->with('status', 'المجاني يسمح بـ 3 أصول — اشترك في برو لمحفظة غير محدودة 💎');
        }

        $data = $request->validate([
            'asset' => 'required|in:'.implode(',', array_keys(self::HOLDING_ASSETS)),
            'amount' => 'required|numeric|min:0.01',
            'buy_price' => 'nullable|numeric|min:0',
            'bought_at' => 'nullable|date|before_or_equal:today',
            'note' => 'nullable|string|max:120',
        ]);
        $user->holdings()->create($data);

        return back()->with('status', 'تمت الإضافة إلى محفظتك');
    }

    public function destroyHolding(Request $request, Holding $holding): RedirectResponse
    {
        abort_unless($holding->user_id === $request->user()->id, 403);
        $holding->delete();

        return back();
    }

    /* ---------------- التنبيهات ---------------- */

    public function alerts(Request $request): View
    {
        $user = $request->user();

        return view('account.alerts', [
            'alerts' => $user->alerts()->with(['instrument', 'city'])->latest()->get(),
            'instruments' => Instrument::whereIn('code', ['usd', 'usd_cbi', 'gold24', 'gold21', 'gold18', 'try', 'irr', 'usdt'])->orderBy('sort')->get(),
            'cities' => City::orderBy('sort')->get(),
            'limit' => $user->alertLimit(),
            'canSpike' => $user->hasPlan('trader'),
            'current' => $this->prices->latest('usd')?->mid,
        ]);
    }

    public function storeAlert(Request $request): RedirectResponse
    {
        $user = $request->user();
        $limit = $user->alertLimit();
        if ($limit !== null && $user->alerts()->count() >= $limit) {
            return back()->with('status', "وصلت للحد الأقصى ({$limit}) في باقتك — رقِّ باقتك لتنبيهات أكثر 💎");
        }

        $data = $request->validate([
            'instrument_id' => 'required|exists:instruments,id',
            'city_id' => 'nullable|exists:cities,id',
            'condition' => 'required|in:above,below,spike',
            'threshold' => 'required_unless:condition,spike|nullable|numeric|min:0',
        ]);
        if ($data['condition'] === 'spike' && ! $user->hasPlan('trader')) {
            return back()->with('status', 'تنبيه الحركات المفاجئة متاح لباقة التاجر 💼');
        }
        $user->alerts()->create($data);

        return back()->with('status', 'تم إنشاء التنبيه 🔔');
    }

    public function destroyAlert(Request $request, Alert $alert): RedirectResponse
    {
        abort_unless($alert->user_id === $request->user()->id, 403);
        $alert->delete();

        return back();
    }

    /* ---------------- التقرير الأسبوعي ---------------- */

    public function weeklyReport(FearIndex $fear, Forecaster $forecaster): View
    {
        $from = today()->subDays(7);
        $closes = $this->prices->dailyCloses('usd', 8);

        return view('account.weekly', [
            'from' => $from,
            'to' => today(),
            'closes' => $closes,
            'usd' => ['open' => $closes->first(), 'close' => $closes->last(), 'high' => $closes->max(), 'low' => $closes->min()],
            'gold' => ['open' => $this->prices->priceAt('gold21', $from), 'close' => $this->prices->latest('gold21')?->mid],
            'cities' => $this->prices->cityBoard(),
            'fear' => $fear->compute(),
            'forecast' => $forecaster->forecast(),
            'news' => NewsItem::where('published_at', '>=', $from)->where('impact', '!=', 'neutral')->orderByDesc('impact_strength')->take(5)->get(),
        ]);
    }
}
