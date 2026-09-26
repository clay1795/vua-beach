#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
project_dir="$(cd -- "$script_dir/.." && pwd -P)"
database_file="$project_dir/database/e2e.sqlite"

rm -f "$database_file"
touch "$database_file"

export APP_ENV=testing
export APP_DEBUG=false
export APP_URL=http://127.0.0.1:8010
export DB_CONNECTION=sqlite
export DB_DATABASE="$database_file"
export CACHE_STORE=array
export E2E_DISABLE_RATE_LIMITS=true
export SESSION_DRIVER=file
export SESSION_DOMAIN=
export SESSION_SECURE_COOKIE=false
export SESSION_SAME_SITE=lax
export SESSION_COOKIE=vua_beach_e2e_session
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array
export GHN_TOKEN=
export GHN_SHOP_ID=
export GHN_FROM_DISTRICT_ID=
export MOMO_ENABLED=false
export VNPAY_ENABLED=true
export PAYMENT_SANDBOX_MODE=false
export VNPAY_URL=https://payments.example.test/pay
export VNPAY_TMN_CODE=E2ETMN
export VNPAY_HASH_SECRET=e2e-vnpay-secret
export VNPAY_RETURN_URL=https://shop.example.test/thanh-toan/vnpay/ket-qua
export VNPAY_IPN_URL=https://shop.example.test/thanh-toan/vnpay/ipn

cd "$project_dir"
php artisan migrate:fresh --force --no-interaction
php artisan db:seed --class=Database\\Seeders\\E2eSeeder --force --no-interaction
cd public
# A single PHP development-server process can be starved by Chromium's parallel
# font/image requests and make navigation appear to hang. Workers keep E2E
# navigation deterministic while the isolated SQLite database remains disposable.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
exec php -S 127.0.0.1:8010 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
