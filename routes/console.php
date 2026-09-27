<?php

use App\Models\Setting;
use App\Services\Alerts\AlertChecker;
use App\Services\Analytics\PredictionScorer;
use App\Services\Analytics\SourceAccuracy;
use App\Services\DemoMarket;
use App\Services\News\NewsFetcher;
use App\Services\Pricing\MarketPipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
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
