#!/usr/bin/env bash
# تجهيز المشروع داخل GitHub Codespaces
set -e
composer install --no-interaction
npm install
[ -f .env ] || cp .env.example .env
php artisan key:generate --force
touch database/database.sqlite
php artisan migrate:fresh --seed --force
npm run build
echo "✅ جاهز — شغّل: php artisan serve --host=0.0.0.0 --port=8000"
