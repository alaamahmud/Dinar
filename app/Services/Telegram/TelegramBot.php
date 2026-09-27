<?php

namespace App\Services\Telegram;

use App\Models\User;
use App\Services\Pricing\PriceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * بوت تيليجرام: يرد على «دولار» و«ذهب» ويرسل تنبيهات المشتركين.
 */
class TelegramBot
{
    public function __construct(private readonly PriceService $prices) {}

    public function enabled(): bool
    {
        return filled(config('dinar.telegram.bot_token'));
    }

    public function send(string $chatId, string $text): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            return Http::timeout(10)
                ->post('https://api.telegram.org/bot'.config('dinar.telegram.bot_token').'/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ])->successful();
        } catch (Throwable $e) {
            Log::warning('Telegram send failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * يعالج رسالة واردة ويعيد نص الرد.
     */
    public function reply(string $chatId, string $text): string
    {
        $text = trim($text);

        if (preg_match('/^(\/link|ربط)\s+([A-Z0-9]{6})$/iu', $text, $m)) {
            $user = User::where('telegram_link_code', strtoupper($m[2]))->first();
            if (! $user) {
                return 'الرمز غير صحيح. انسخه من صفحة حسابك في الموقع.';
            }
            $user->forceFill(['telegram_chat_id' => $chatId, 'telegram_link_code' => null])->save();

            return "تم ربط حسابك ✅ ستصلك التنبيهات هنا يا {$user->name}.";
        }

        if (preg_match('/(ذهب|الذهب|gold)/iu', $text)) {
            return $this->goldMessage();
        }

        if (preg_match('/(دولار|الدولار|usd|\$|سعر)/iu', $text)) {
            return $this->usdMessage();
        }

        return "أهلاً بك في بوت نبض الدينار 👋\n\nاكتب:\n• <b>دولار</b> — سعر الدولار الآن\n• <b>ذهب</b> — سعر الذهب\n• <b>ربط الرمز</b> — لربط حسابك واستقبال التنبيهات";
    }

    public function usdMessage(): string
    {
        $lines = ["💵 <b>سعر 100 دولار الآن</b>\n"];
        foreach ($this->prices->cityBoard() as $row) {
            if ($row['snapshot']) {
                $lines[] = sprintf('%s: شراء %s | بيع %s', $row['city']->name_ar, number_format($row['snapshot']->buy), number_format($row['snapshot']->sell));
            }
        }
        $cbi = $this->prices->latest('usd_cbi');
        if ($cbi) {
            $lines[] = "\n🏦 الرسمي: ".number_format($cbi->mid);
        }
        $lines[] = "\n".config('app.url');

        return implode("\n", $lines);
    }

    public function goldMessage(): string
    {
        $lines = ["🥇 <b>سعر مثقال الذهب</b>\n"];
        foreach (['gold24' => 'عيار 24', 'gold21' => 'عيار 21', 'gold18' => 'عيار 18'] as $code => $label) {
            $snap = $this->prices->latest($code);
            if ($snap) {
                $lines[] = "{$label}: ".number_format($snap->mid).' دينار';
            }
        }
        $lines[] = "\n".config('app.url');

        return implode("\n", $lines);
    }
}
