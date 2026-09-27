<?php

use App\Models\Setting;
use App\Services\Alerts\AlertChecker;
use App\Services\Analytics\PredictionScorer;
use App\Services\Analytics\SourceAccuracy;
use App\Services\DemoMarket;
use App\Services\News\NewsFetcher;
use App\Services\Pricing\MarketPipeline;
use App\Services\Pricing\Sources\TelegramChannelSource;
use App\Services\Pricing\TextPriceParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('dinar:tick', function (MarketPipeline $pipeline, AlertChecker $alerts) {
    if (Setting::get('demo_mode')) {
        $this->line('وضع تجريبي: توليد قراءات محاكاة');
        app(DemoMarket::class)->tick();
    }
    foreach ($pipeline->fetchAll() as $source => $result) {
        $this->line("{$source}: {$result}");
    }
    $this->info('أسعار محسوبة: '.$pipeline->aggregateAll());
    $this->info('تنبيهات مرسلة: '.$alerts->run());
})->purpose('جلب الأسعار من المصادر وحسابها وإرسال التنبيهات');

Artisan::command('dinar:news', function (NewsFetcher $fetcher) {
    $this->info('أخبار جديدة: '.$fetcher->fetch());
})->purpose('جلب الأخبار من خلاصات RSS وتحليلها');

Artisan::command('dinar:score {date?}', function (PredictionScorer $scorer, ?string $date = null) {
    $date = $date ? Carbon::parse($date) : today()->subDay();
    $this->info('توقعات مصححة: '.$scorer->score($date));
})->purpose('تصحيح توقعات المسابقة لليوم المحدد (افتراضياً أمس)');

Artisan::command('dinar:accuracy', function (SourceAccuracy $accuracy) {
    $accuracy->refresh();
    $this->info('تم تحديث دقة المصادر وثقة المبلّغين');
})->purpose('قياس دقة المصادر وتحديث ثقة المستخدمين');

Schedule::command('dinar:tick')->everyMinute()->withoutOverlapping();
Schedule::command('dinar:news')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('dinar:score')->dailyAt('00:10');
Schedule::command('dinar:accuracy')->dailyAt('03:00');

Artisan::command('dinar:check-channel {channel}', function (TelegramChannelSource $driver, TextPriceParser $parser, string $channel) {
    $channel = ltrim(preg_replace('#^(https?://)?t\.me/(s/)?#', '', trim($channel)), '@');
    $this->info("فحص القناة: {$channel}");

    try {
        $response = Http::timeout(15)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DinarPulse/1.0)'])
            ->get("https://t.me/s/{$channel}");
    } catch (Throwable $e) {
        $this->error('تعذر الاتصال بتيليجرام: '.$e->getMessage());

        return 1;
    }

    $messages = $driver->extractMessages($response->body());
    if ($messages === []) {
        $this->error('❌ لا توجد منشورات ظاهرة — القناة غير موجودة أو خاصة أو تمنع المعاينة على الويب.');

        return 1;
    }

    $found = 0;
    foreach (array_slice($messages, -10) as $message) {
        $prices = $parser->parse($message['text']);
        $found += count($prices);
        $this->line(str_repeat('─', 50));
        $this->line(($message['time'] ?? '').'  '.mb_substr(str_replace("\n", ' ⏎ ', $message['text']), 0, 160));
        foreach ($prices as $p) {
            $this->info(sprintf('   ✅ %s | شراء %s | بيع %s%s', $p['city'] ?? 'المدينة الافتراضية', number_format((float) $p['buy']), number_format((float) $p['sell']), $p['note_type'] ? " ({$p['note_type']})" : ''));
        }
        if (! $prices) {
            $this->comment('   — لم يُستخرج سعر من هذا المنشور');
        }
    }

    $this->line(str_repeat('─', 50));
    $found > 0
        ? $this->info("✅ القناة صالحة: استُخرج {$found} سعر من آخر ".min(10, count($messages)).' منشورات. أضفها من لوحة الإدارة.')
        : $this->warn('⚠️ القناة تفتح لكن لم يُفهم أي سعر — أرسل نص المنشورات للمطوّر لتعديل المحلل.');

    return 0;
})->purpose('فحص قناة تيليجرام عامة: هل يمكن قراءة أسعارها؟');
