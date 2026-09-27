<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\BasketItem;
use App\Models\CbiAuction;
use App\Models\City;
use App\Models\Holding;
use App\Models\Instrument;
use App\Models\NewsItem;
use App\Models\Place;
use App\Models\PlaceReview;
use App\Models\Prediction;
use App\Models\PriceReading;
use App\Models\Setting;
use App\Models\Source;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Notifications\PriceAlertNotification;
use App\Services\Analytics\PredictionScorer;
use App\Services\Analytics\SourceAccuracy;
use App\Services\DemoMarket;
use App\Services\News\RuleBasedAnalyzer;
use App\Services\Pricing\GoldCalculator;
use App\Services\Pricing\MarketPipeline;
use App\Services\Pricing\Sources\CbiSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * بيانات تجريبية واقعية الشكل (ليست أسعاراً حقيقية) لعرض المنصة وتجربتها.
 */
class DemoSeeder extends Seeder
{
    private const DAYS = 400;

    /** @var array<int, float> ساعة => سعر 100 دولار */
    private array $usdPath = [];

    /** @var array<int, float> */
    private array $ouncePath = [];

    /** @var array<int, float> */
    private array $silverPath = [];

    private Carbon $end;

    public function run(): void
    {
        mt_srand(42);
        Setting::put('demo_mode', true);
        Instrument::flushCodeCache();

        $this->end = now()->startOfHour();
        $this->buildPaths();
        $this->seedSnapshots();
        $users = $this->seedUsers();
        $this->seedReadings($users);
        $this->seedLive();
        $this->seedPredictions($users);
        $this->seedPlaces($users);
        $this->seedNews();
        $this->seedAuctions();
        $this->seedBasket($users);
        $this->seedPersonal();

        app(SourceAccuracy::class)->refresh();
    }

    private function gauss(): float
    {
        return app(DemoMarket::class)->gauss();
    }

    /**
     * مسار سعري ساعي: عملية عودة للمتوسط + أحداث (قفزات) + ضجيج.
     */
    private function buildPaths(): void
    {
        $hours = self::DAYS * 24;
        $daily = [];
        $x = 147000.0;
        for ($d = 0; $d <= self::DAYS; $d++) {
            $x += 0.04 * (145000 - $x) + $this->gauss() * 320;
            $daysAgo = self::DAYS - $d;
            // حدث: قفزة حادة قبل ~8 أشهر ثم تراجع تدريجي
            $event = $daysAgo <= 250 && $daysAgo >= 200 ? 4200 * exp(-(250 - $daysAgo) / 12) : 0;
            // ارتفاع خفيف قبل ~3 أشهر
            $event += $daysAgo <= 95 && $daysAgo >= 60 ? 1500 * exp(-(95 - $daysAgo) / 10) : 0;
            // صعود هادئ آخر أسبوعين
            $event += $daysAgo <= 14 ? (14 - $daysAgo) * 70 : 0;
            $daily[$d] = $x + $event;
        }

        for ($h = 0; $h <= $hours; $h++) {
            $d = intdiv($h, 24);
            $frac = ($h % 24) / 24;
            $base = $daily[$d] + (($daily[min($d + 1, self::DAYS)] ?? $daily[$d]) - $daily[$d]) * $frac;
            $this->usdPath[$h] = round($base + $this->gauss() * 60, -1);

            $t = $h / $hours;
            $this->ouncePath[$h] = round(3300 + 600 * $t + 40 * sin($t * 18) + $this->gauss() * 6, 2);
            $this->silverPath[$h] = round(36 + 10 * $t + 1.2 * sin($t * 14) + $this->gauss() * 0.08, 3);
        }
    }

    private function at(int $h): Carbon
    {
        return $this->end->copy()->subHours(self::DAYS * 24 - $h);
    }

    private function seedSnapshots(): void
    {
        $gold = app(GoldCalculator::class);
        $ids = Instrument::pluck('id', 'code');
        $cities = City::all()->keyBy('slug');
        $primary = $cities['baghdad-kifah']->id;
        $cross = Setting::get('fx_cross');
        $hours = self::DAYS * 24;
        $rows = [];
        $now = now();

        $push = function (string $code, int $h, float $mid, float $spread, ?int $cityId = null, ?string $note = null) use (&$rows, $ids, $now) {
            $rows[] = [
                'instrument_id' => $ids[$code],
                'city_id' => $cityId,
                'note_type' => $note,
                'buy' => round($mid * (1 - $spread / 2), 2),
                'sell' => round($mid * (1 + $spread / 2), 2),
                'mid' => round($mid, 2),
                'confidence' => 'high',
                'sample_count' => mt_rand(3, 9),
                'computed_at' => $this->at($h),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($rows) >= 500) {
                DB::table('price_snapshots')->insert($rows);
                $rows = [];
            }
        };

        for ($h = 0; $h <= $hours; $h++) {
            $usd = $this->usdPath[$h];
            $push('usd', $h, $usd, 0.0017, $primary);

            $recent = $h >= $hours - 60 * 24;
            if ($recent && $h % 2 === 0) {
                foreach ($cities as $slug => $city) {
                    if ($city->id !== $primary) {
                        $push('usd', $h, $usd + DemoMarket::CITY_OFFSETS[$slug] + $this->gauss() * 40, 0.002, $city->id);
                    }
                }
                $push('usd', $h, $usd * 0.988, 0.002, $primary, 'white');
                $push('usd', $h, $usd * 0.994, 0.002, $primary, 'small');
            }

            if ($h % 2 === 0) {
                $ounce = $this->ouncePath[$h];
                $silver = $this->silverPath[$h];
                $push('gold_ounce', $h, $ounce, 0.0005);
                $push('silver_ounce', $h, $silver, 0.001);
                foreach ([24 => 'gold24', 21 => 'gold21', 18 => 'gold18'] as $karat => $code) {
                    $push($code, $h, $gold->mithqalPrice($karat, $ounce, $usd, 0.01), 0.01);
                }
                $push('silver', $h, $silver / 31.1035 * $usd / 100, 0.02);
                $push('usdt', $h, $usd * 1.004, 0.003);
                $perOne = $usd / 100;
                $push('sar', $h, $perOne / $cross['sar'], 0.004);
                $push('jod', $h, $perOne / $cross['jod'], 0.004);
                // الليرة والتومان يفقدان قيمتهما تدريجياً
                $t = $h / $hours;
                $push('try', $h, $perOne / ($cross['try'] * (0.82 + 0.18 * $t)), 0.008);
                $push('irr', $h, 1000 / $perOne * $cross['irr_toman'] * (0.75 + 0.25 * $t), 0.01);
            }
            if ($h % 6 === 0) {
                $push('usd_cbi', $h, 131000, 0.0);
            }
        }

        DB::table('price_snapshots')->insert($rows);
    }

    /**
     * @return Collection<int, User>
     */
    private function seedUsers()
    {
        User::factory()->create([
            'name' => 'مدير المنصة', 'email' => 'admin@dinar.test', 'is_admin' => true,
            'plan' => 'trader', 'plan_expires_at' => now()->addYear(),
        ]);
        User::factory()->create([
            'name' => 'أحمد (مشترك برو)', 'email' => 'pro@dinar.test',
            'plan' => 'pro', 'plan_expires_at' => now()->addDays(24), 'monthly_salary' => 1250000,
        ]);
        User::factory()->create([
            'name' => 'صيرفة تجريبية (باقة تاجر)', 'email' => 'trader@dinar.test',
            'plan' => 'trader', 'plan_expires_at' => now()->addMonths(3),
        ]);
        User::factory()->create(['name' => 'زائر مسجّل', 'email' => 'free@dinar.test']);

        $names = ['علي حسين', 'مصطفى كريم', 'زينب علي', 'حسن جبار', 'نور الهدى', 'محمد باقر', 'سجاد عباس', 'فاطمة ناصر',
            'عمر خالد', 'حيدر سلمان', 'مريم فاضل', 'يوسف أكرم', 'سارة مهدي', 'كرار حميد', 'آيات رعد', 'ليث صباح',
            'رسل قاسم', 'منتظر هادي', 'هبة سعد', 'أمير طارق', 'دعاء ماجد', 'باسم عادل', 'غدير جاسم', 'وسام فراس', 'تبارك زيد'];

        return collect($names)->map(fn ($name, $i) => User::factory()->create([
            'name' => $name,
            'email' => "user{$i}@dinar.test",
            'trust_score' => 0.5,
        ]));
    }

    /**
     * قراءات من «قنوات» محاكاة وبلاغات مستخدمين خلال آخر أسبوعين (لقياس الدقة).
     */
    private function seedReadings($users): void
    {
        $channels = [
            ['محاكاة: قناة أسعار البورصة', 0.0006],
            ['محاكاة: قناة الصرافين', 0.0009],
            ['محاكاة: قناة أخبار السوق (أقل دقة)', 0.004],
        ];
        $usd = Instrument::byCode('usd');
        $primary = City::primary()->id;
        $hours = self::DAYS * 24;

        foreach ($channels as [$name, $noise]) {
            $source = Source::firstOrCreate(['name' => $name], ['type' => 'manual', 'weight' => $noise > 0.002 ? 0.5 : 1.0]);
            $rows = [];
            for ($h = $hours - 14 * 24; $h < $hours; $h += 2) {
                $mid = $this->usdPath[$h] * (1 + $this->gauss() * $noise);
                $rows[] = [
                    'instrument_id' => $usd->id, 'city_id' => $primary, 'source_id' => $source->id,
                    'buy' => round($mid - 125), 'sell' => round($mid + 125),
                    'recorded_at' => $this->at($h)->subMinutes(mt_rand(5, 40)),
                    'external_id' => "demo-{$source->id}-{$h}",
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            DB::table('price_readings')->insert($rows);
            $source->update(['readings_count' => count($rows), 'last_fetched_at' => now()->subMinutes(2)]);
        }

        $crowd = Source::ofType('crowd');
        foreach ($users as $i => $user) {
            $skill = 0.0005 + ($i % 5) * 0.0015;
            for ($k = 0; $k < mt_rand(3, 10); $k++) {
                $h = $hours - mt_rand(1, 14 * 24);
                $mid = $this->usdPath[$h] * (1 + $this->gauss() * $skill);
                PriceReading::create([
                    'instrument_id' => $usd->id, 'city_id' => $primary, 'source_id' => $crowd->id, 'user_id' => $user->id,
                    'buy' => round($mid, -1), 'sell' => round($mid, -1),
                    'recorded_at' => $this->at($h)->subMinutes(mt_rand(5, 40)),
                ]);
            }
        }
    }

    /**
     * آخر 40 دقيقة: قراءات حية ثم حساب الأسعار الحالية كما يفعل النظام كل دقيقة.
     */
    private function seedLive(): void
    {
        $demo = app(DemoMarket::class);
        $pipeline = app(MarketPipeline::class);

        foreach ([50, 40, 30, 20, 10, 0] as $minutesAgo) {
            $at = now()->subMinutes($minutesAgo);
            $demo->tick($at);
            app(CbiSource::class)->fetch(Source::ofType('cbi'));
            $pipeline->aggregateAll($at);
        }
    }

    private function seedPredictions($users): void
    {
        $usd = Instrument::byCode('usd');
        $hours = self::DAYS * 24;

        foreach ($users as $i => $user) {
            $skill = 0.002 + ($i % 7) * 0.003;
            for ($d = 30; $d >= 1; $d--) {
                if (mt_rand(1, 10) > 7) {
                    continue;
                }
                $close = $this->usdPath[$hours - $d * 24 + 23] ?? end($this->usdPath);
                Prediction::create([
                    'user_id' => $user->id, 'instrument_id' => $usd->id,
                    'target_date' => today()->subDays($d),
                    'predicted' => round($close * (1 + $this->gauss() * $skill), -1),
                ]);
            }
        }

        $scorer = app(PredictionScorer::class);
        for ($d = 30; $d >= 1; $d--) {
            $scorer->score(today()->subDays($d));
        }

        // توقعات الغد (لم تُصحح بعد) — منها «توقع الجمهور»
        $last = end($this->usdPath);
        foreach ($users->take(18) as $user) {
            Prediction::create([
                'user_id' => $user->id, 'instrument_id' => $usd->id, 'target_date' => today()->addDay(),
                'predicted' => round($last * (1 + 0.002 + $this->gauss() * 0.004), -1),
            ]);
        }
    }

    private function seedPlaces($users): void
    {
        $cities = City::all()->keyBy('slug');
        $places = [
            ['صيرفة النخيل', 'exchange', 'baghdad-kifah', 'شارع الكفاح، قرب ساحة الشهداء', 0.004, -0.003],
            ['صيرفة دجلة', 'exchange', 'baghdad-kifah', 'شارع الكفاح', -0.002, 0.004],
            ['مكتب الرافدين للصرافة', 'exchange', 'baghdad-harthiya', 'الحارثية، قرب المعرض', 0.003, 0.002],
            ['صيرفة الجسر', 'exchange', 'baghdad-harthiya', 'الحارثية', -0.004, -0.002],
            ['صيرفة القلعة', 'exchange', 'erbil', 'مركز أربيل، قرب القلعة', 0.002, 0.003],
            ['صيرفة شط العرب', 'exchange', 'basra', 'العشار', 0.003, -0.002],
            ['صيرفة الكوفة', 'exchange', 'najaf', 'شارع الكوفة', 0.002, 0.002],
            ['صيرفة الحدباء', 'exchange', 'mosul', 'الدواسة', -0.002, 0.003],
            ['مجوهرات اللؤلؤة', 'gold', 'baghdad-kifah', 'سوق الذهب، شارع النهر', 0.006, -0.006],
            ['ذهب الأمانة', 'gold', 'baghdad-harthiya', 'المنصور', 0.005, 0.005],
            ['مجوهرات الفرات', 'gold', 'karbala', 'قرب باب القبلة', 0.002, -0.003],
            ['ذهب السليمانية الذهبي', 'gold', 'sulaymaniyah', 'سوق الذهب', -0.003, 0.002],
            ['مجوهرات البصرة', 'gold', 'basra', 'سوق الذهب، العشار', -0.002, 0.004],
        ];

        foreach ($places as $i => [$name, $type, $slug, $address, $dLat, $dLng]) {
            $city = $cities[$slug];
            $place = Place::create([
                'name' => $name, 'type' => $type, 'city_id' => $city->id, 'address' => $address,
                'lat' => $city->lat + $dLat, 'lng' => $city->lng + $dLng, 'approved' => true,
                'phone' => '0770'.str_pad((string) (1000000 + $i * 7919), 7, '0', STR_PAD_LEFT),
                'featured_until' => $i % 5 === 0 ? now()->addMonth() : null,
            ]);
            foreach ($users->random(mt_rand(2, 6)) as $user) {
                PlaceReview::create([
                    'place_id' => $place->id, 'user_id' => $user->id, 'rating' => mt_rand(3, 5),
                    'comment' => collect(['تعامل ممتاز وسعر عادل', 'سريعين بالصرف', 'السعر قريب من البورصة', 'زحمة بس محترمين', null])->random(),
                ]);
            }
            $place->refreshRating();
        }

        Place::create([
            'name' => 'صيرفة جديدة بانتظار المراجعة', 'type' => 'exchange', 'city_id' => $cities['najaf']->id,
            'address' => 'شارع الإمام علي', 'approved' => false, 'added_by' => $users->first()->id,
        ]);
    }

    private function seedNews(): void
    {
        $rules = app(RuleBasedAnalyzer::class);
        $news = [
            ['البنك المركزي يعلن ارتفاع مبيعات نافذة بيع العملة إلى مستويات قياسية', 'أعلن البنك المركزي العراقي ارتفاع مبيعاته من الدولار خلال الأسبوع الحالي، في خطوة تهدف لتلبية الطلب وتسهيل عمليات التحويل للتجار.', 2],
            ['تقارير عن تشديد جديد على التحويلات الخارجية لبعض المصارف', 'تحدثت تقارير عن قيود إضافية على تحويلات عدد من المصارف الأهلية، ما قد ينعكس على الطلب على الدولار النقدي في السوق.', 8],
            ['وزارة المالية: تمويل رواتب الموظفين لهذا الشهر دون تأخير', 'أكدت وزارة المالية إطلاق تمويل رواتب الموظفين في موعدها، مع استقرار الإيرادات النفطية.', 20],
            ['ارتفاع أسعار النفط عالمياً مع تراجع المخزونات', 'سجلت أسعار خام برنت ارتفاعاً خلال تداولات اليوم، وهو ما يدعم إيرادات الموازنة العامة.', 30],
            ['الفيدرالي الأمريكي يلمح إلى إبقاء أسعار الفائدة مرتفعة لفترة أطول', 'قال مسؤولون في الفيدرالي الأمريكي إن الفائدة قد تبقى مرتفعة، ما يعزز قوة الدولار عالمياً.', 44],
            ['أسعار الذهب تسجل مستوى مرتفعاً جديداً مع الطلب على الملاذات الآمنة', 'واصل الذهب مكاسبه عالمياً مع ازدياد حالة عدم اليقين في الأسواق.', 52],
            ['مخاوف من توتر إقليمي تدفع الطلب على الدولار في الأسواق المحلية', 'رصد متعاملون ارتفاعاً في الطلب على الدولار النقدي مع أنباء عن تصعيد إقليمي.', 60],
            ['البنك المركزي: احتياطيات العملة الأجنبية عند مستويات مطمئنة', 'أكد البنك المركزي أن احتياطياته من العملة الأجنبية تعزز استقرار سعر الصرف.', 75],
            ['تجار: هدوء نسبي في بورصة الكفاح مع بداية الأسبوع', 'شهدت بورصة الكفاح تداولات هادئة واستقراراً في الأسعار مقارنة بالأسبوع الماضي.', 90],
            ['حديث عن تسهيلات جديدة لاستيراد السلع عبر المنصة الإلكترونية', 'يتوقع تجار أن تسهم التسهيلات الجديدة في تخفيف الطلب على السوق الموازي.', 110],
        ];

        foreach ($news as $i => [$title, $body, $hoursAgo]) {
            NewsItem::create([
                'title' => $title, 'body' => $body, 'source_name' => 'خبر تجريبي',
                'url' => "https://example.com/demo-news-{$i}", 'is_demo' => true,
                'published_at' => now()->subHours($hoursAgo),
                ...$rules->analyze($title, $body), 'analyzed_by' => 'rules',
            ]);
        }
    }

    private function seedAuctions(): void
    {
        for ($d = 120; $d >= 1; $d--) {
            $date = today()->subDays($d);
            if (in_array($date->dayOfWeek, [5, 6], true)) {
                continue; // لا مزاد الجمعة والسبت
            }
            $sales = 200 + 60 * sin($d / 9) + $this->gauss() * 15;
            $cash = $sales * (0.08 + mt_rand(0, 6) / 100);
            CbiAuction::create([
                'date' => $date, 'sales_musd' => round($sales, 1), 'cash_musd' => round($cash, 1),
                'transfers_musd' => round($sales - $cash, 1), 'official_rate' => 1310,
            ]);
        }
    }

    private function seedBasket($users): void
    {
        $base = ['tomato' => 1000, 'flour' => 900, 'rice' => 2000, 'oil' => 2500, 'sugar' => 1250, 'eggs' => 5000,
            'chicken' => 4250, 'gas' => 6000, 'ampere' => 10000, 'fuel' => 850, 'rent' => 350000];
        $cities = City::pluck('id')->all();
        $rows = [];

        foreach (BasketItem::all() as $item) {
            for ($m = 11; $m >= 0; $m--) {
                $inflation = 1 + (11 - $m) * 0.006 + ($item->slug === 'tomato' ? 0.25 * sin($m / 2) : 0) + ($item->slug === 'ampere' ? (in_array(now()->subMonths($m)->month, [6, 7, 8]) ? 0.4 : 0) : 0);
                foreach ($cities as $cityId) {
                    for ($k = 0; $k < 2; $k++) {
                        $rows[] = [
                            'basket_item_id' => $item->id, 'city_id' => $cityId, 'user_id' => $users->random()->id,
                            'price' => (int) round($base[$item->slug] * $inflation * (1 + $this->gauss() * 0.05), -1),
                            'reported_at' => now()->subMonths($m)->startOfMonth()->addDays(mt_rand(0, 25))->min(now()),
                            'created_at' => now(), 'updated_at' => now(),
                        ];
                    }
                }
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('basket_prices')->insert($chunk);
        }
    }

    private function seedPersonal(): void
    {
        $pro = User::where('email', 'pro@dinar.test')->first();
        $usd = Instrument::byCode('usd');
        $gold21 = Instrument::byCode('gold21');

        Holding::create(['user_id' => $pro->id, 'asset' => 'usd', 'amount' => 2500, 'buy_price' => 1462, 'bought_at' => today()->subMonths(5), 'note' => 'مدخرات']);
        Holding::create(['user_id' => $pro->id, 'asset' => 'gold21', 'amount' => 25, 'buy_price' => 140000, 'bought_at' => today()->subMonths(9), 'note' => 'ذهب زوجتي']);
        Holding::create(['user_id' => $pro->id, 'asset' => 'iqd', 'amount' => 3500000, 'note' => 'نقد في البيت']);

        Alert::create(['user_id' => $pro->id, 'instrument_id' => $usd->id, 'condition' => 'above', 'threshold' => 147500]);
        Alert::create(['user_id' => $pro->id, 'instrument_id' => $usd->id, 'condition' => 'below', 'threshold' => 143000]);
        Alert::create(['user_id' => $pro->id, 'instrument_id' => $gold21->id, 'condition' => 'above', 'threshold' => 700000]);
        Alert::create(['user_id' => $pro->id, 'instrument_id' => $usd->id, 'condition' => 'spike']);

        $pro->notify(new PriceAlertNotification('ارتفع الدولار (السوق الموازي) إلى 146,250 (حدّك: 146,000)', 146250));

        $free = User::where('email', 'free@dinar.test')->first();
        SubscriptionRequest::create(['user_id' => $free->id, 'plan' => 'pro', 'months' => 1, 'payment_method' => 'zaincash', 'reference' => 'ZC-58213']);
        SubscriptionRequest::create(['user_id' => User::where('email', 'user3@dinar.test')->first()->id, 'plan' => 'trader', 'months' => 3, 'payment_method' => 'qi', 'reference' => 'QI-99120']);
    }
}
