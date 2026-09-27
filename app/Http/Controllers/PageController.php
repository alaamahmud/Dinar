<?php

namespace App\Http\Controllers;

use App\Models\CbiAuction;
use App\Models\City;
use App\Models\Instrument;
use App\Models\NewsItem;
use App\Models\Prediction;
use App\Models\Setting;
use App\Models\Source;
use App\Models\User;
use App\Services\Analytics\AnomalyDetector;
use App\Services\Analytics\FearIndex;
use App\Services\Analytics\Forecaster;
use App\Services\Pricing\GoldCalculator;
use App\Services\Pricing\PriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private readonly PriceService $prices) {}

    public function home(Request $request, FearIndex $fear, Forecaster $forecaster, AnomalyDetector $anomalies): View
    {
        $viewer = $request->user();
        $quotes = $this->quotes(['usd', 'usd_cbi', 'gold21', 'gold24', 'gold18', 'silver'], $viewer);
        $cutoff = $this->prices->cutoffFor($viewer);
        $primary = City::primary();

        // التحليلات ثقيلة نسبياً — تُخزن مؤقتاً 5 دقائق
        $analytics = Cache::remember('home.analytics', 300, fn () => [
            'fear' => $fear->compute(),
            'forecast' => $forecaster->forecast(),
            'anomaly' => $anomalies->detect(),
        ]);

        $crowd = Prediction::where('instrument_id', Instrument::byCode('usd')->id)
            ->whereDate('target_date', today()->addDay())
            ->pluck('predicted');

        return view('pages.home', [
            'quotes' => $quotes,
            'primary' => $primary,
            'day' => $this->prices->dayStats('usd', today(), null, $cutoff),
            'notes' => [
                'white' => $this->prices->quote('usd', $viewer, null, 'white'),
                'small' => $this->prices->quote('usd', $viewer, null, 'small'),
            ],
            'cities' => $this->prices->cityBoard($viewer),
            'others' => $this->quotes(['irr', 'try', 'sar', 'jod', 'usdt'], $viewer),
            'fear' => $analytics['fear'],
            'forecast' => $analytics['forecast'],
            'anomaly' => $analytics['anomaly'],
            'crowdForecast' => $crowd->isNotEmpty() ? round($crowd->median(), -1) : null,
            'crowdCount' => $crowd->count(),
            'news' => NewsItem::latest('published_at')->take(4)->get(),
            'delayed' => $this->prices->isDelayedFor($viewer),
        ]);
    }

    public function gold(Request $request, GoldCalculator $gold): View
    {
        $viewer = $request->user();
        $usd = $this->prices->usdRate($viewer);
        $ounce = $this->prices->latest('gold_ounce', null, null, $this->prices->cutoffFor($viewer))?->mid;
        $silverGram = $this->prices->latest('silver', null, null, $this->prices->cutoffFor($viewer))?->mid;

        $check = null;
        if ($request->filled(['grams', 'offered']) && $ounce) {
            $data = $request->validate([
                'grams' => 'required|numeric|min:0.1|max:5000',
                'karat' => 'required|in:24,22,21,18',
                'origin' => 'required|in:'.implode(',', array_keys(GoldCalculator::ORIGINS)),
                'offered' => 'required|numeric|min:1',
                'side' => 'required|in:buy,sell',
            ]);
            $check = $gold->fairPrice((float) $data['grams'], (int) $data['karat'], $data['origin'], (float) $data['offered'], $ounce, $usd, $data['side']);
        }

        $table = [];
        if ($ounce) {
            foreach (GoldCalculator::KARATS as $karat) {
                $gram = $gold->gramPrice($karat, $ounce, $usd);
                $table[] = [
                    'karat' => $karat,
                    'gram' => $gram,
                    'mithqal' => $gram * 5,
                    'scrap' => $gram * 5 * (1 - (float) Setting::get('scrap_discount')),
                ];
            }
        }

        return view('pages.gold', [
            'quotes' => $this->quotes(['gold24', 'gold21', 'gold18', 'gold_ounce', 'silver', 'silver_ounce'], $viewer),
            'table' => $table,
            'making' => Setting::get('making_charges'),
            'origins' => GoldCalculator::ORIGINS,
            'nisab' => $ounce ? $gold->nisab($ounce, $usd, $silverGram) : null,
            'check' => $check,
            'delayed' => $this->prices->isDelayedFor($viewer),
        ]);
    }

    public function currencies(Request $request): View
    {
        return view('pages.currencies', [
            'quotes' => $this->quotes(['usd', 'usd_cbi', 'usdt', 'irr', 'try', 'sar', 'jod'], $request->user()),
            'delayed' => $this->prices->isDelayedFor($request->user()),
        ]);
    }

    public function markets(Request $request): View
    {
        $viewer = $request->user();
        $board = $this->prices->cityBoard($viewer);
        $withPrices = $board->filter(fn ($r) => $r['snapshot']);

        return view('pages.markets', [
            'board' => $board,
            'cheapest' => $withPrices->sortBy(fn ($r) => $r['snapshot']->buy)->first(),
            'priciest' => $withPrices->sortByDesc(fn ($r) => $r['snapshot']->sell)->first(),
            'auctions' => CbiAuction::orderByDesc('date')->take(60)->get()->reverse()->values(),
            'usdSeries' => collect($this->prices->series('usd', '1y'))->groupBy(fn ($p) => substr($p['t'], 0, 10))->map(fn ($g) => $g->last()['v']),
            'isTrader' => (bool) $viewer?->hasPlan('trader'),
            'delayed' => $this->prices->isDelayedFor($viewer),
        ]);
    }

    public function news(Request $request): View
    {
        $impact = $request->query('impact');

        return view('pages.news', [
            'items' => NewsItem::when(in_array($impact, ['up', 'down', 'neutral'], true), fn ($q) => $q->where('impact', $impact))
                ->latest('published_at')->paginate(15)->withQueryString(),
            'impact' => $impact,
            'isPro' => (bool) $request->user()?->hasPlan('pro'),
            'aiEnabled' => (bool) Setting::get('ai_enabled') && filled(config('dinar.ai.api_key')),
        ]);
    }

    public function sources(): View
    {
        return view('pages.sources', [
            'sources' => Source::orderByRaw('accuracy IS NULL')->orderBy('accuracy')->get(),
            'reporters' => User::whereHas('reports')->withCount('reports')->orderByDesc('trust_score')->take(10)->get(),
        ]);
    }

    public function pricing(): View
    {
        return view('pages.pricing', [
            'plans' => config('dinar.plans'),
            'methods' => config('dinar.payment_methods'),
        ]);
    }

    public function share(Request $request): View
    {
        return view('pages.share', [
            'quotes' => $this->quotes(['usd', 'usd_cbi', 'gold21', 'gold24'], $request->user()),
            'cities' => $this->prices->cityBoard($request->user())->take(4),
        ]);
    }

    public function widget(): View
    {
        return view('pages.widget');
    }

    public function embed(Request $request): View
    {
        return view('pages.embed', [
            'quotes' => $this->quotes(['usd', 'gold21'], null),
            'theme' => $request->query('theme') === 'dark' ? 'dark' : 'light',
        ]);
    }

    public function display(string $token): View
    {
        $user = User::where('display_token', $token)->firstOrFail();
        abort_unless($user->hasPlan('trader'), 403, 'شاشة المحل متاحة لباقة التاجر فقط');

        return view('pages.display', [
            'owner' => $user,
            'quotes' => $this->quotes(['usd', 'gold24', 'gold21', 'gold18', 'try', 'irr', 'sar'], $user),
            'notes' => [
                'white' => $this->prices->quote('usd', $user, null, 'white'),
                'small' => $this->prices->quote('usd', $user, null, 'small'),
            ],
        ]);
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, array<string, mixed>|null>
     */
    private function quotes(array $codes, ?User $viewer): array
    {
        return collect($codes)->mapWithKeys(fn ($code) => [$code => $this->prices->quote($code, $viewer)])->all();
    }
}
