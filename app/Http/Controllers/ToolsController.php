<?php

namespace App\Http\Controllers;

use App\Services\Calculators;
use App\Services\Pricing\PriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ToolsController extends Controller
{
    public const TOOLS = [
        'what-if' => ['title' => 'لو اشتريت…', 'icon' => '⏳', 'desc' => 'كم تساوي اليوم لو حوّلت مبلغاً إلى دولار أو ذهب في تاريخ سابق؟', 'plan' => null],
        'salary' => ['title' => 'راتبك الحقيقي', 'icon' => '🧾', 'desc' => 'قيمة راتبك بالدولار والذهب عبر الأشهر — كم خسر أو ربح؟', 'plan' => null],
        'zakat' => ['title' => 'حاسبة الزكاة', 'icon' => '🤲', 'desc' => 'النصاب بسعر اليوم ومقدار الزكاة على النقد والدولار والذهب', 'plan' => null],
        'car' => ['title' => 'كلفة السيارة المستوردة', 'icon' => '🚗', 'desc' => 'سعر المزاد + الشحن + الجمرك + الترسيم = التكلفة بالدينار', 'plan' => null],
        'remittance' => ['title' => 'مقارنة الحوالات', 'icon' => '💸', 'desc' => 'أي طريقة توصلك أكبر مبلغ من حوالة قادمة من الخارج؟', 'plan' => null],
        'travel' => ['title' => 'ميزانية السفر', 'icon' => '✈️', 'desc' => 'كم تحتاج لرحلة إلى إيران أو تركيا أو السعودية أو الأردن؟', 'plan' => null],
        'trader' => ['title' => 'حاسبة التاجر', 'icon' => '💼', 'desc' => 'ربح صفقات الصرف الكبيرة، الهامش، ونقطة التعادل', 'plan' => 'trader'],
    ];

    public function __construct(
        private readonly Calculators $calc,
        private readonly PriceService $prices,
    ) {}

    public function index(): View
    {
        return view('tools.index', ['tools' => self::TOOLS]);
    }

    public function show(Request $request, string $tool): View
    {
        $meta = self::TOOLS[$tool];
        $viewer = $request->user();
        $locked = $meta['plan'] && ! $viewer?->hasPlan($meta['plan']);
        $usd = $this->prices->usdRate($viewer);
        $result = null;

        if (! $locked && $request->query('go')) {
            $result = match ($tool) {
                'what-if' => $this->whatIf($request),
                'salary' => $this->calc->salaryValue((float) $request->validate(['salary' => 'required|numeric|min:1000'])['salary']),
                'zakat' => $this->zakat($request, $usd),
                'car' => $this->car($request, $usd),
                'remittance' => $this->remittance($request, $usd),
                'travel' => $this->travel($request, $usd),
                'trader' => $this->trader($request),
            };
        }

        return view('tools.'.$tool, [
            'tool' => $tool,
            'meta' => $meta,
            'locked' => $locked,
            'usd' => $usd,
            'result' => $result,
            'salaryDefault' => $viewer?->monthly_salary,
            'destinations' => Calculators::DESTINATIONS,
            'assets' => Calculators::ASSETS,
        ]);
    }

    private function whatIf(Request $request): ?array
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'asset' => 'required|in:usd,gold21,gold24',
            'date' => 'required|date|before:today|after:2020-01-01',
        ]);

        return $this->calc->whatIf((float) $data['amount'], $data['asset'], Carbon::parse($data['date']));
    }

    private function zakat(Request $request, float $usd): array
    {
        $data = $request->validate([
            'cash' => 'nullable|numeric|min:0',
            'dollars' => 'nullable|numeric|min:0',
            'gold' => 'nullable|numeric|min:0',
        ]);

        return $this->calc->zakat(
            (float) ($data['cash'] ?? 0),
            (float) ($data['dollars'] ?? 0),
            (float) ($data['gold'] ?? 0),
            $usd,
            $this->prices->latest('gold_ounce')?->mid,
            $this->prices->latest('silver')?->mid,
        );
    }

    private function car(Request $request, float $usd): array
    {
        $data = $request->validate([
            'price' => 'required|numeric|min:100',
            'shipping' => 'nullable|numeric|min:0',
        ]);

        return $this->calc->carImport((float) $data['price'], (float) ($data['shipping'] ?? 0), $usd);
    }

    private function remittance(Request $request, float $usd): array
    {
        $data = $request->validate(['amount' => 'required|numeric|min:10|max:1000000']);

        return $this->calc->remittance(
            (float) $data['amount'],
            $usd,
            $this->prices->latest('usd_cbi')?->mid,
            $this->prices->latest('usdt')?->mid,
        );
    }

    private function travel(Request $request, float $usd): array
    {
        $data = $request->validate([
            'destination' => 'required|in:'.implode(',', array_keys(Calculators::DESTINATIONS)),
            'days' => 'required|integer|min:1|max:90',
            'people' => 'required|integer|min:1|max:50',
            'daily' => 'nullable|numeric|min:1|max:5000',
        ]);

        return $this->calc->travel($data['destination'], (int) $data['days'], (int) $data['people'], $usd, isset($data['daily']) ? (float) $data['daily'] : null);
    }

    private function trader(Request $request): array
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'buy' => 'required|numeric|min:1000',
            'sell' => 'required|numeric|min:1000',
            'costs' => 'nullable|numeric|min:0',
        ]);

        return $this->calc->trade((float) $data['amount'], (float) $data['buy'], (float) $data['sell'], (float) ($data['costs'] ?? 0));
    }
}
