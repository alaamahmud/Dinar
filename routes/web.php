<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ToolsController;
use Illuminate\Support\Facades\Route;

// الصفحات العامة
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/gold', [PageController::class, 'gold'])->name('gold');
Route::get('/currencies', [PageController::class, 'currencies'])->name('currencies');
Route::get('/markets', [PageController::class, 'markets'])->name('markets');
Route::get('/news', [PageController::class, 'news'])->name('news');
Route::get('/sources', [PageController::class, 'sources'])->name('sources');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/share/today', [PageController::class, 'share'])->name('share');
Route::get('/widget', [PageController::class, 'widget'])->name('widget');
Route::get('/embed', [PageController::class, 'embed'])->name('embed');
Route::get('/display/{token}', [PageController::class, 'display'])->name('display');

// المجتمع
Route::get('/predict', [CommunityController::class, 'predictions'])->name('predict');
Route::get('/places', [CommunityController::class, 'places'])->name('places');
Route::get('/basket', [CommunityController::class, 'basket'])->name('basket');

// الحاسبات
Route::get('/tools', [ToolsController::class, 'index'])->name('tools');
Route::get('/tools/{tool}', [ToolsController::class, 'show'])
    ->whereIn('tool', ['what-if', 'salary', 'zakat', 'car', 'remittance', 'travel', 'trader'])
    ->name('tools.show');

// بيانات للواجهة والمطورين
Route::prefix('api')->group(function () {
    Route::get('/live', [ApiController::class, 'live'])->name('api.live');
    Route::get('/series/{code}', [ApiController::class, 'series'])->name('api.series');
    Route::get('/v1/prices', [ApiController::class, 'v1Prices'])->middleware('throttle:60,1')->name('api.v1.prices');
});
Route::post('/telegram/webhook/{secret}', [ApiController::class, 'telegram'])->name('telegram.webhook');

// الدخول والتسجيل
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/report', [CommunityController::class, 'reportForm'])->name('report');
    Route::post('/report', [CommunityController::class, 'storeReport'])->middleware('throttle:6,10');
    Route::post('/predict', [CommunityController::class, 'storePrediction']);
    Route::post('/places', [CommunityController::class, 'storePlace'])->name('places.store');
    Route::post('/places/{place}/review', [CommunityController::class, 'review'])->name('places.review');
    Route::post('/basket', [CommunityController::class, 'storeBasket'])->middleware('throttle:20,10');

    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::post('/account', [AccountController::class, 'update']);
    Route::post('/notifications/read', [AccountController::class, 'readNotifications'])->name('notifications.read');
    Route::post('/subscribe', [AccountController::class, 'subscribe'])->name('subscribe');

    Route::get('/portfolio', [AccountController::class, 'portfolio'])->name('portfolio');
    Route::post('/portfolio', [AccountController::class, 'storeHolding']);
    Route::delete('/portfolio/{holding}', [AccountController::class, 'destroyHolding'])->name('portfolio.destroy');

    Route::get('/alerts', [AccountController::class, 'alerts'])->name('alerts');
    Route::post('/alerts', [AccountController::class, 'storeAlert']);
    Route::delete('/alerts/{alert}', [AccountController::class, 'destroyAlert'])->name('alerts.destroy');

    Route::get('/weekly-report', [AccountController::class, 'weeklyReport'])->middleware('plan:pro')->name('weekly');
    Route::get('/export/prices.csv', [ApiController::class, 'export'])->middleware('plan:trader')->name('export');
});

// لوحة الإدارة
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::post('/prices', [AdminController::class, 'storePrice'])->name('prices');
    Route::post('/tick', [AdminController::class, 'tick'])->name('tick');
    Route::post('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('/sources/{source}', [AdminController::class, 'updateSource'])->name('sources.update');
    Route::post('/sources', [AdminController::class, 'storeSource'])->name('sources.store');
    Route::post('/subscriptions/{subscription}/{action}', [AdminController::class, 'handleSubscription'])
        ->whereIn('action', ['approve', 'reject'])->name('subscriptions');
    Route::post('/places/{place}/approve', [AdminController::class, 'approvePlace'])->name('places.approve');
    Route::delete('/readings/{reading}', [AdminController::class, 'destroyReading'])->name('readings.destroy');
    Route::post('/news', [AdminController::class, 'storeNews'])->name('news.store');
});
