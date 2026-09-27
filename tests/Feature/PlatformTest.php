<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Instrument;
use App\Models\PriceReading;
use App\Models\Source;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Services\Pricing\MarketPipeline;
use App\Services\Pricing\Sources\TelegramChannelSource;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceSeeder::class);
        Instrument::flushCodeCache();

        // قراءات حالية للدولار والأونصة ثم حساب الأسعار
        $usd = Instrument::byCode('usd');
        $source = Source::create(['name' => 'اختبار', 'type' => 'manual', 'weight' => 1]);
        foreach (City::all() as $city) {
            foreach ([145000, 145100, 145050] as $i => $p) {
                PriceReading::create(['instrument_id' => $usd->id, 'city_id' => $city->id, 'source_id' => $source->id, 'buy' => $p - 100, 'sell' => $p + 100, 'recorded_at' => now()->subMinutes(20 + $i)]);
            }
        }
        PriceReading::create(['instrument_id' => Instrument::byCode('gold_ounce')->id, 'source_id' => $source->id, 'buy' => 3900, 'sell' => 3900, 'recorded_at' => now()->subMinutes(20)]);
        app(MarketPipeline::class)->aggregateAll(now()->subMinutes(16));
    }

    public function test_public_pages_render(): void
    {
        foreach (['/', '/gold', '/currencies', '/markets', '/news', '/sources', '/pricing', '/predict', '/places', '/basket', '/tools', '/tools/zakat', '/share/today', '/embed'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('145,050');
    }

    public function test_gold_is_derived_from_ounce_and_usd(): void
    {
        $this->getJson('/api/live')->assertOk()->assertJsonPath('prices.usd.mid', 145050)
            ->assertJsonStructure(['prices' => ['gold21', 'sar', 'jod']]);
    }

    public function test_free_users_cannot_see_full_history(): void
    {
        $this->getJson('/api/series/usd?range=1y')->assertJsonPath('locked', true);
        $pro = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addMonth()]);
        $this->actingAs($pro)->getJson('/api/series/usd?range=1y')->assertJsonPath('locked', false);
    }

    public function test_crowd_report_is_validated_against_market(): void
    {
        $user = User::factory()->create();
        $city = City::primary();

        $this->actingAs($user)->post('/report', ['city_id' => $city->id, 'side' => 'buy', 'price' => 160000])->assertSessionHasErrors('price');
        $this->actingAs($user)->post('/report', ['city_id' => $city->id, 'side' => 'buy', 'price' => 145200])->assertSessionHasNoErrors();
        $this->assertSame(5, $user->fresh()->points);
    }

    public function test_plan_gates_and_api_token(): void
    {
        $free = User::factory()->create();
        $this->actingAs($free)->get('/export/prices.csv')->assertRedirect(route('pricing'));

        $trader = User::factory()->create(['plan' => 'trader', 'plan_expires_at' => now()->addMonth()]);
        $token = $trader->ensureApiToken();
        $this->getJson('/api/v1/prices?token='.$token)->assertOk()->assertJsonStructure(['data' => [['code', 'buy', 'sell']]]);
        $this->getJson('/api/v1/prices?token=wrong')->assertStatus(401);

        $expired = User::factory()->create(['plan' => 'trader', 'plan_expires_at' => now()->subDay()]);
        $this->assertSame('free', $expired->activePlan());
    }

    public function test_admin_approves_subscription(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/subscribe', ['plan' => 'pro', 'months' => 3, 'payment_method' => 'zaincash', 'reference' => 'X1']);
        $request = SubscriptionRequest::first();

        $this->actingAs($user)->post("/admin/subscriptions/{$request->id}/approve")->assertForbidden();
        $this->actingAs($admin)->post("/admin/subscriptions/{$request->id}/approve")->assertRedirect();
        $this->assertTrue($user->fresh()->hasPlan('pro'));
    }

    public function test_telegram_channel_html_is_parsed(): void
    {
        $html = '<div class="tgme_widget_message js-widget_message" data-post="chan/77"><div class="tgme_widget_message_text js-message_text">بورصة الكفاح<br>بيع 145,500 شراء 145,300</div><time datetime="'.now()->subMinutes(3)->toIso8601String().'"></time></div>';
        $driver = app(TelegramChannelSource::class);
        $source = Source::create(['name' => 't', 'type' => 'telegram', 'config' => ['channel' => 'chan']]);

        $messages = $driver->extractMessages($html);
        $this->assertSame('chan/77', $messages[0]['id']);
        $this->assertSame(1, $driver->ingest($source, $messages));
        $this->assertSame(0, $driver->ingest($source, $messages)); // لا تكرار
        $this->assertSame(145300.0, PriceReading::where('source_id', $source->id)->first()->buy);
    }
}
