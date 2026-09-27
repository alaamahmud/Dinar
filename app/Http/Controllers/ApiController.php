<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Instrument;
use App\Models\PriceSnapshot;
use App\Models\User;
use App\Services\Pricing\PriceService;
use App\Services\Telegram\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiController extends Controller
{
    public function __construct(private readonly PriceService $prices) {}

    /**
     * آخر الأسعار للتحديث التلقائي في الصفحة (كل 30 ثانية).
     */
    public function live(Request $request): JsonResponse
    {
        $viewer = $request->user();
        // شاشة المحل تعمل بدون تسجيل دخول، برمز العرض الخاص بالتاجر
        if (! $viewer && $request->filled('display')) {
            $viewer = User::where('display_token', $request->query('display'))->first();
        }
        $data = [];
        foreach (['usd', 'usd_cbi', 'gold24', 'gold21', 'gold18', 'silver', 'irr', 'try', 'sar', 'jod', 'usdt'] as $code) {
            $quote = $this->prices->quote($code, $viewer);
            if ($quote) {
                $data[$code] = [
                    'buy' => $quote['snapshot']->buy,
                    'sell' => $quote['snapshot']->sell,
                    'mid' => $quote['snapshot']->mid,
                    'change_pct' => $quote['change_pct'],
                    'confidence' => $quote['snapshot']->confidence,
                    'at' => $quote['snapshot']->computed_at->toIso8601String(),
                    'decimals' => $quote['instrument']->decimals,
                ];
            }
        }

        return response()->json([
            'delayed' => $this->prices->isDelayedFor($viewer),
            'prices' => $data,
        ]);
    }

    public function series(Request $request, string $code): JsonResponse
    {
        $instrument = Instrument::where('code', $code)->firstOrFail();
        $range = (string) $request->query('range', '30d');
        $viewer = $request->user();

        // المجاني: آخر 30 يوماً فقط
        $locked = in_array($range, ['1y', 'all'], true) && ! $viewer?->hasPlan('pro');
        if ($locked) {
            return response()->json(['locked' => true, 'points' => []]);
        }

        $cityId = $request->integer('city') ?: null;

        return response()->json([
            'locked' => false,
            'instrument' => $instrument->name_ar,
            'points' => $this->prices->series($code, $range, $cityId, $this->prices->cutoffFor($viewer), $request->query('note') ?: null),
        ]);
    }

    /**
     * واجهة برمجية للمطورين (باقة التاجر): /api/v1/prices?token=...
     */
    public function v1Prices(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->query('token');
        $user = $token ? User::where('api_token', $token)->first() : null;

        if (! $user || ! $user->hasPlan('trader')) {
            return response()->json(['error' => 'رمز غير صالح أو الباقة لا تشمل الواجهة البرمجية'], 401);
        }

        $out = [];
        foreach (Instrument::orderBy('sort')->get() as $instrument) {
            if ($instrument->has_cities) {
                foreach (City::orderBy('sort')->get() as $city) {
                    $snap = $this->prices->latest($instrument->code, $city->id);
                    if ($snap) {
                        $out[] = $this->row($instrument, $snap, $city);
                    }
                }
            } elseif ($snap = $this->prices->latest($instrument->code)) {
                $out[] = $this->row($instrument, $snap);
            }
        }

        return response()->json(['data' => $out, 'generated_at' => now()->toIso8601String()]);
    }

    /**
     * تصدير تاريخ الأسعار CSV (يفتح في Excel) — باقة التاجر.
     */
    public function export(Request $request): StreamedResponse
    {
        $code = $request->query('code', 'usd');
        $instrument = Instrument::where('code', $code)->firstOrFail();
        $days = min(3650, max(1, $request->integer('days', 90)));

        return response()->streamDownload(function () use ($instrument, $days) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM ليقرأ Excel العربية بشكل صحيح
            fputcsv($out, ['الوقت', 'الأداة', 'المدينة', 'شراء', 'بيع', 'الوسط', 'الثقة']);
            PriceSnapshot::with('city')
                ->where('instrument_id', $instrument->id)
                ->whereNull('note_type')
                ->where('computed_at', '>=', now()->subDays($days))
                ->orderBy('computed_at')
                ->chunk(1000, function ($rows) use ($out, $instrument) {
                    foreach ($rows as $row) {
                        fputcsv($out, [$row->computed_at->format('Y-m-d H:i'), $instrument->name_ar, $row->city?->market_ar ?? '', $row->buy, $row->sell, $row->mid, $row->confidence]);
                    }
                });
            fclose($out);
        }, "dinar-{$instrument->code}-{$days}d.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function telegram(Request $request, string $secret, TelegramBot $bot): JsonResponse
    {
        abort_unless(filled(config('dinar.telegram.webhook_secret')) && hash_equals((string) config('dinar.telegram.webhook_secret'), $secret), 403);

        $chatId = $request->input('message.chat.id');
        $text = (string) $request->input('message.text', '');
        if ($chatId) {
            $bot->send((string) $chatId, $bot->reply((string) $chatId, $text));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Instrument $instrument, PriceSnapshot $snap, ?City $city = null): array
    {
        return [
            'code' => $instrument->code,
            'name' => $instrument->name_ar,
            'unit' => $instrument->unit_ar,
            'city' => $city?->slug,
            'market' => $city?->market_ar,
            'buy' => $snap->buy,
            'sell' => $snap->sell,
            'mid' => $snap->mid,
            'confidence' => $snap->confidence,
            'at' => $snap->computed_at->toIso8601String(),
        ];
    }
}
