<?php

namespace Database\Seeders;

use App\Models\BasketItem;
use App\Models\City;
use App\Models\Instrument;
use App\Models\Setting;
use App\Models\Source;
use Illuminate\Database\Seeder;

/**
 * البيانات الأساسية التي يحتاجها النظام في الإنتاج أيضاً.
 */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $instruments = [
            ['usd', 'الدولار (السوق الموازي)', 'دينار لكل 100 دولار', 'currency', '$', 0, true],
            ['usd_cbi', 'الدولار (السعر الرسمي)', 'دينار لكل 100 دولار', 'currency', '$', 0, false],
            ['gold24', 'ذهب عيار 24', 'دينار للمثقال', 'metal', null, 0, false],
            ['gold21', 'ذهب عيار 21', 'دينار للمثقال', 'metal', null, 0, false],
            ['gold18', 'ذهب عيار 18', 'دينار للمثقال', 'metal', null, 0, false],
            ['gold_ounce', 'أونصة الذهب العالمية', 'دولار للأونصة', 'metal', null, 2, false],
            ['silver', 'الفضة', 'دينار للغرام', 'metal', null, 0, false],
            ['silver_ounce', 'أونصة الفضة العالمية', 'دولار للأونصة', 'metal', null, 2, false],
            ['usdt', 'الدولار الرقمي USDT', 'دينار لكل 100 USDT', 'currency', '₮', 0, false],
            ['irr', 'التومان الإيراني', 'تومان مقابل 1,000 دينار', 'currency', null, 0, false],
            ['try', 'الليرة التركية', 'دينار لكل ليرة', 'currency', '₺', 1, false],
            ['sar', 'الريال السعودي', 'دينار لكل ريال', 'currency', null, 1, false],
            ['jod', 'الدينار الأردني', 'دينار عراقي لكل دينار أردني', 'currency', null, 0, false],
        ];
        foreach ($instruments as $i => [$code, $name, $unit, $category, $symbol, $decimals, $hasCities]) {
            Instrument::updateOrCreate(['code' => $code], [
                'name_ar' => $name, 'unit_ar' => $unit, 'category' => $category,
                'symbol' => $symbol, 'decimals' => $decimals, 'has_cities' => $hasCities, 'sort' => $i,
            ]);
        }

        $cities = [
            ['baghdad-kifah', 'بغداد', 'بورصة الكفاح', 33.3406, 44.4009, true],
            ['baghdad-harthiya', 'بغداد', 'بورصة الحارثية', 33.3152, 44.3661, false],
            ['baghdad-samawal', 'بغداد', 'سوق السموأل', null, null, false],
            ['erbil', 'أربيل', 'سوق أربيل', 36.1911, 44.0092, false],
            ['sulaymaniyah', 'السليمانية', 'سوق السليمانية', 35.5613, 45.4309, false],
            ['basra', 'البصرة', 'سوق البصرة', 30.5085, 47.7804, false],
            ['najaf', 'النجف', 'سوق النجف', 32.0259, 44.3462, false],
            ['karbala', 'كربلاء', 'سوق كربلاء', 32.6160, 44.0249, false],
            ['mosul', 'الموصل', 'سوق الموصل', 36.3350, 43.1189, false],
            ['duhok', 'دهوك', 'سوق دهوك', 36.8669, 42.9503, false],
        ];
        foreach ($cities as $i => [$slug, $name, $market, $lat, $lng, $primary]) {
            City::updateOrCreate(['slug' => $slug], [
                'name_ar' => $name, 'market_ar' => $market, 'lat' => $lat, 'lng' => $lng,
                'is_primary' => $primary, 'sort' => $i,
            ]);
        }

        $sources = [
            ['بلاغات المستخدمين', 'crowd', null, 1.0, true],
            ['البنك المركزي العراقي (سعر رسمي)', 'cbi', null, 1.0, true],
            ['GoldAPI — أونصة الذهب', 'gold_api', ['metal' => 'XAU'], 1.0, false],
            ['GoldAPI — أونصة الفضة', 'gold_api', ['metal' => 'XAG'], 1.0, false],
            ['مؤشر USDT في P2P', 'p2p', null, 0.5, false],
            // مثال لقناة تيليجرام — ضع اسم قناة عامة حقيقية وفعّلها من لوحة الإدارة
            ['قناة تيليجرام (مثال)', 'telegram', ['channel' => 'example_channel', 'default_city' => 'baghdad-kifah'], 1.0, false],
        ];
        foreach ($sources as [$name, $type, $config, $weight, $enabled]) {
            Source::firstOrCreate(['name' => $name], [
                'type' => $type, 'config' => $config, 'weight' => $weight, 'enabled' => $enabled,
            ]);
        }

        $basket = [
            ['tomato', 'طماطم', 'كغم', 1.0],
            ['flour', 'طحين', 'كغم', 1.5],
            ['rice', 'رز', 'كغم', 1.5],
            ['oil', 'زيت طبخ', 'لتر', 1.0],
            ['sugar', 'سكر', 'كغم', 1.0],
            ['eggs', 'بيض', 'طبقة (30)', 1.0],
            ['chicken', 'دجاج', 'كغم', 1.5],
            ['gas', 'قنينة غاز', 'قنينة', 1.0],
            ['ampere', 'أمبير المولدة', 'أمبير/شهر', 1.5],
            ['fuel', 'بنزين', 'لتر', 1.0],
            ['rent', 'إيجار شقة صغيرة', 'شهرياً', 2.0],
        ];
        foreach ($basket as $i => [$slug, $name, $unit, $weight]) {
            BasketItem::updateOrCreate(['slug' => $slug], ['name_ar' => $name, 'unit_ar' => $unit, 'weight' => $weight, 'sort' => $i]);
        }

        foreach (Setting::DEFAULTS as $key => $value) {
            if (! Setting::find($key)) {
                Setting::put($key, $value);
            }
        }
        if (! Setting::find('fx_cross')) {
            Setting::put('fx_cross', ['sar' => 3.75, 'jod' => 0.709, 'try' => 41.5, 'irr_toman' => 100000]);
        }
        if (! Setting::find('news_feeds')) {
            Setting::put('news_feeds', []);
        }
    }
}
