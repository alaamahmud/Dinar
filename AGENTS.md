# نبض الدينار (Dinar Pulse)

Laravel 13 app (PHP 8.3+, Blade + Tailwind v4 + Chart.js, RTL Arabic UI) showing Iraqi dinar exchange rates and gold prices.

## Commands
- Setup: `composer install && npm install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed && npm run build`
- Dev server: `php artisan serve` (+ `npm run dev` for hot reload)
- Tests: `php artisan test`
- Code style: `vendor/bin/pint`
- Price cycle (runs every minute via scheduler): `php artisan dinar:tick`

## Layout
- `app/Services/Pricing` — source drivers, text parser, weighted-median aggregator, derived prices (gold/SAR/JOD…)
- `app/Services/Analytics` — anomaly detector, forecaster, fear index, source accuracy, basket index, prediction scoring
- `app/Services/News` — RSS fetcher; rule-based analyzer with optional Claude analyzer (toggle in admin)
- `database/seeders/DemoSeeder.php` — simulated demo data (never real prices); `demo_mode` setting shows a banner
- USD prices are stored per 100 dollars; gold per mithqal (5 g).
