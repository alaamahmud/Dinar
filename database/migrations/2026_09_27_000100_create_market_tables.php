<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // الأدوات المسعّرة: الدولار الموازي، الرسمي، الذهب بأعياره، العملات الأخرى...
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name_ar');
            $table->string('unit_ar');
            $table->string('category', 16); // currency | metal | index
            $table->string('symbol', 8)->nullable();
            $table->unsignedTinyInteger('decimals')->default(0);
            $table->boolean('has_cities')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // المدن والأسواق (بورصة الكفاح، الحارثية، أربيل...)
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name_ar');
            $table->string('market_ar')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // مصادر البيانات: قنوات تيليجرام، واجهات برمجية، بلاغات المستخدمين...
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 24); // telegram | gold_api | p2p | cbi | crowd | manual
            $table->json('config')->nullable();
            $table->decimal('weight', 4, 2)->default(1);
            $table->boolean('enabled')->default(true);
            $table->decimal('accuracy', 6, 3)->nullable(); // متوسط الانحراف % عن السعر النهائي
            $table->unsignedInteger('readings_count')->default(0);
            $table->timestamp('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        // كل قراءة خام من أي مصدر
        Schema::create('price_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('buy', 16, 2)->nullable();
            $table->decimal('sell', 16, 2)->nullable();
            $table->string('note_type', 16)->nullable(); // white | blue | small
            $table->string('external_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
            $table->unique(['source_id', 'external_id']);
            $table->index(['instrument_id', 'city_id', 'recorded_at']);
        });

        // السعر المحسوب (الوسيط الموزون) — هذا ما يُعرض للناس
        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note_type', 16)->nullable();
            $table->decimal('buy', 16, 2);
            $table->decimal('sell', 16, 2);
            $table->decimal('mid', 16, 2);
            $table->string('confidence', 8)->default('medium'); // high | medium | low
            $table->unsignedSmallInteger('sample_count')->default(1);
            $table->timestamp('computed_at')->index();
            $table->timestamps();
            $table->index(['instrument_id', 'city_id', 'note_type', 'computed_at'], 'snap_lookup');
        });

        // مسابقة التوقع اليومية
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('target_date');
            $table->decimal('predicted', 16, 2);
            $table->decimal('actual', 16, 2)->nullable();
            $table->decimal('error_pct', 8, 4)->nullable();
            $table->integer('points')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'instrument_id', 'target_date']);
        });

        // دليل الصرافين ومحلات الذهب
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16); // exchange | gold
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->boolean('approved')->default(false);
            $table->timestamp('featured_until')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();
        });

        Schema::create('place_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 500)->nullable();
            $table->timestamps();
            $table->unique(['place_id', 'user_id']);
        });

        // رادار الأخبار
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('url')->nullable()->unique();
            $table->string('source_name')->nullable();
            $table->text('body')->nullable();
            $table->text('summary')->nullable();
            $table->string('impact', 8)->default('neutral'); // up | down | neutral (أثره على سعر الدولار)
            $table->unsignedTinyInteger('impact_strength')->default(0); // 0-3
            $table->string('analyzed_by', 8)->nullable(); // ai | rules
            $table->boolean('is_demo')->default(false);
            $table->timestamp('published_at')->index();
            $table->timestamps();
        });

        // تنبيهات الأسعار
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('condition', 8); // above | below | spike
            $table->decimal('threshold', 16, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });

        // المحفظة الشخصية
        Schema::create('holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset', 32); // iqd أو كود أداة
            $table->decimal('amount', 18, 4);
            $table->decimal('buy_price', 16, 2)->nullable(); // سعر الشراء بالدينار للوحدة المعروضة
            $table->date('bought_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // سلة المواطن
        Schema::create('basket_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name_ar');
            $table->string('unit_ar');
            $table->decimal('weight', 5, 2)->default(1); // وزن السلعة في المؤشر
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('basket_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basket_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('price');
            $table->timestamp('reported_at')->index();
            $table->timestamps();
        });

        // مبيعات نافذة بيع العملة في البنك المركزي
        Schema::create('cbi_auctions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('sales_musd', 10, 2); // مليون دولار
            $table->decimal('cash_musd', 10, 2)->nullable();
            $table->decimal('transfers_musd', 10, 2)->nullable();
            $table->unsignedInteger('official_rate');
            $table->timestamps();
        });

        // طلبات الاشتراك (دفع يدوي يوافق عليه المدير)
        Schema::create('subscription_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 16);
            $table->unsignedTinyInteger('months')->default(1);
            $table->string('payment_method', 24);
            $table->string('reference')->nullable();
            $table->string('status', 12)->default('pending'); // pending | approved | rejected
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        // إعدادات قابلة للتعديل من لوحة الإدارة
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'settings', 'subscription_requests', 'cbi_auctions', 'basket_prices', 'basket_items',
            'holdings', 'alerts', 'news_items', 'place_reviews', 'places', 'predictions',
            'price_snapshots', 'price_readings', 'sources', 'cities', 'instruments',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
