#!/bin/sh
# İlk açılışta bağımlılıkları kurar, .env üretir, sonra geliştirme sunucusunu başlatır.
set -e
cd /var/www/pnlcs

[ -f .env ] || cp docker/env.dev .env
[ -d vendor ] || composer install --no-interaction --prefer-dist
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
[ -d node_modules ] || npm install --no-audit --no-fund
[ -f public/build/manifest.json ] || npm run build

until mysqladmin ping -h db -u pnlcs -ppnlcs --silent; do echo "DB bekleniyor..."; sleep 2; done

php artisan storage:link 2>/dev/null || true
exec php artisan serve --host 0.0.0.0 --port 8000
