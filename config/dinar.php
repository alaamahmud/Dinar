<?php

return [

    /*
    |--------------------------------------------------------------------------
    | إعدادات منصة نبض الدينار
    |--------------------------------------------------------------------------
    */

    // تأخير الأسعار لغير المشتركين (بالدقائق)
    'free_delay_minutes' => 15,

    // نافذة جمع القراءات لحساب السعر (بالدقائق)
    'aggregation_window_minutes' => 60,

    // أي قراءة تبتعد عن الوسيط بأكثر من هذه النسبة تُستبعد
    'outlier_threshold' => 0.015,

    // حدود منطقية لسعر 100 دولار بالدينار (لفلترة الأرقام الخاطئة)
    'usd_sane_range' => [100000, 250000],

    // وزن المثقال بالغرام
    'mithqal_grams' => 5,

    'grams_per_ounce' => 31.1035,

    'ai' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_MODEL', 'claude-haiku-4-5'),
    ],

    'gold_api' => [
        'url' => env('GOLD_API_URL', 'https://www.goldapi.io/api/XAU/USD'),
        'key' => env('GOLD_API_KEY'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    /*
    | الباقات والميزات
    */
    'plans' => [
        'free' => [
            'name' => 'مجاني',
            'price' => 0,
            'alerts' => 1,
            'history_days' => 30,
        ],
        'pro' => [
            'name' => 'برو',
            'price' => 5000,
            'alerts' => 20,
            'history_days' => null,
        ],
        'trader' => [
            'name' => 'تاجر',
            'price' => 25000,
            'alerts' => null,
            'history_days' => null,
        ],
    ],

    // طرق الدفع اليدوي المعروضة في صفحة الاشتراك
    'payment_methods' => [
        'zaincash' => 'زين كاش',
        'qi' => 'كي كارد / سوبر كي',
        'asiahawala' => 'آسيا حوالة',
        'fastpay' => 'FastPay',
    ],
];
