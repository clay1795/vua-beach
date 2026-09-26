#!/usr/bin/env bash

# Run from the Laravel project root after safely updating the release files.
# This script intentionally does not pull Git code, delete files, or reset data.
set -euo pipefail

if [[ ! -f artisan ]]; then
  echo "Run this script from the Laravel project root." >&2
  exit 1
fi

if [[ -z "${EXPECTED_APP_ENV:-}" ]]; then
  echo "Set EXPECTED_APP_ENV to the intended target (for example: production)." >&2
  exit 1
fi

current_environment="$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo $app->environment();')"
if [[ "$current_environment" != "$EXPECTED_APP_ENV" ]]; then
  echo "Refusing to deploy: expected ${EXPECTED_APP_ENV}, detected ${current_environment:-unknown}." >&2
  exit 1
fi

configured_app_url="$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo rtrim((string) config("app.url"), "/");')"
release_health_url="${RELEASE_HEALTH_URL:-$configured_app_url}"
release_health_url="${release_health_url%/}"
if [[ "$release_health_url" != https://* ]]; then
  echo "Refusing to deploy: RELEASE_HEALTH_URL must use HTTPS." >&2
  exit 1
fi
if [[ "$release_health_url" != "$configured_app_url" ]]; then
  echo "Refusing to deploy: RELEASE_HEALTH_URL must match APP_URL exactly." >&2
  exit 1
fi

release_stamp="$(date +%Y%m%d-%H%M%S)"
backup_dir="storage/app/backups"
runtime_group="${APP_RUNTIME_GROUP:-www-data}"
runtime_user="${APP_RUNTIME_USER:-www-data}"
if [[ ! -d "$backup_dir" ]]; then
  mkdir -p "$backup_dir"
  chmod 700 "$backup_dir"
fi

if ! getent group "$runtime_group" >/dev/null 2>&1; then
  echo "Refusing to deploy: runtime group does not exist: $runtime_group" >&2
  exit 1
fi
if ! id "$runtime_user" >/dev/null 2>&1; then
  echo "Refusing to deploy: runtime user does not exist: $runtime_user" >&2
  exit 1
fi

run_runtime_artisan() {
  sudo -n -u "$runtime_user" php artisan "$@"
}
backup_mode="$(stat -c '%a' "$backup_dir")"
if (( (0$backup_mode & 0007) != 0 )); then
  echo "Refusing to deploy: backup directory must not be accessible by other users (detected mode: $backup_mode)." >&2
  exit 1
fi
if [[ ! -w "$backup_dir" ]]; then
  echo "Refusing to deploy: release user cannot write to backup directory: $backup_dir" >&2
  exit 1
fi

composer validate --no-check-publish --no-interaction
composer audit --locked --no-interaction
if [[ "${BUILD_FRONTEND:-false}" == "true" ]]; then
  command -v npm >/dev/null 2>&1 || { echo "BUILD_FRONTEND=true but npm is unavailable." >&2; exit 1; }
  npm ci
  npm audit --audit-level=high
  npm run build
fi

# Refuse an unsafe release before maintenance mode or any data mutation.
php artisan vua-beach:preflight --strict --allow-pending-migrations
php artisan vua-beach:alert-test
if php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(config("sentry.dsn") ? 0 : 1);'; then
  php artisan sentry:test
elif php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(Illuminate\Support\Facades\Schema::hasTable("exception_incidents") ? 0 : 1);'; then
  run_runtime_artisan vua-beach:incident-test
else
  echo "Internal incident probe deferred until its pending migration has run."
fi

# Freeze writes before taking the rollback snapshot so orders, stock and uploads
# cannot drift between backup and migration.
previous_config_cache="$backup_dir/config-cache-before-$release_stamp.php"
had_previous_config_cache=false
if [[ -f bootstrap/cache/config.php ]]; then
  cp bootstrap/cache/config.php "$previous_config_cache"
  chmod 600 "$previous_config_cache"
  had_previous_config_cache=true
fi

maintenance_active=false
release_cleanup() {
  local status=$?
  trap - EXIT

  if [[ "$status" -ne 0 && "$maintenance_active" == true ]]; then
    echo "Release failed; restoring the previous cached configuration before reopening the site." >&2
    if [[ "$had_previous_config_cache" == true ]]; then
      install -m 640 -g "$runtime_group" "$previous_config_cache" bootstrap/cache/config.php
    fi
    php artisan up || true
  fi

  exit "$status"
}
trap release_cleanup EXIT

php artisan down --retry=60 --refresh=15
maintenance_active=true

php artisan db:backup --path="$backup_dir"

if [[ -d storage/app/public ]]; then
  tar -czf "$backup_dir/uploads-$release_stamp.tar.gz" -C storage/app public
  chmod 600 "$backup_dir/uploads-$release_stamp.tar.gz"
fi

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan storage:link --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
chgrp "$runtime_group" bootstrap/cache/*.php
chmod 640 bootstrap/cache/config.php
php artisan vua-beach:preflight --strict
if php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(config("sentry.dsn") ? 0 : 1);'; then
  php artisan sentry:test
else
  run_runtime_artisan vua-beach:incident-test
fi
php artisan queue:restart || true

# schedule:work is a long-running process and optimize:clear removed its cached
# heartbeat. On Supervisor deployments, restart it before the health smoke test
# so the release cannot wait on a stale heartbeat. The value is a program name,
# never an arbitrary shell command.
scheduler_program="${SUPERVISOR_SCHEDULER_PROGRAM:-}"
if [[ -n "$scheduler_program" ]]; then
  if [[ ! "$scheduler_program" =~ ^[A-Za-z0-9_-]+$ ]]; then
    echo "Refusing to deploy: invalid SUPERVISOR_SCHEDULER_PROGRAM." >&2
    exit 1
  fi
  command -v supervisorctl >/dev/null 2>&1 || { echo "Supervisor program configured but supervisorctl is unavailable." >&2; exit 1; }
  sudo -n supervisorctl restart "$scheduler_program"
fi

php artisan up
maintenance_active=false
rm -f "$previous_config_cache"
trap - EXIT

health_ready=false
for ((attempt=1; attempt<=12; attempt++)); do
  if curl --fail --silent --show-error --max-time 20 --output /dev/null "${release_health_url}/health"; then
    health_ready=true
    break
  fi
  sleep 10
done
if [[ "$health_ready" != true ]]; then
  echo "Release smoke test failed: /health did not become ready within 120 seconds." >&2
  exit 1
fi

for public_path in / /dang-nhap /san-pham; do
  curl --fail --silent --show-error --location --max-time 20 \
    --output /dev/null "${release_health_url}${public_path}"
done

headers_file="$(mktemp)"
trap 'rm -f "$headers_file"' EXIT
curl --fail --silent --show-error --location --max-time 20 \
  --dump-header "$headers_file" --output /dev/null "${release_health_url}/dang-nhap"
if ! grep -qi '^strict-transport-security:' "$headers_file"; then
  echo "Release smoke test failed: HTTPS response is missing Strict-Transport-Security." >&2
  exit 1
fi
cookie_headers="$(grep -i '^set-cookie:' "$headers_file" || true)"
if [[ -z "$cookie_headers" ]]; then
  echo "Release smoke test failed: login response did not issue a session cookie." >&2
  exit 1
fi
if printf '%s\n' "$cookie_headers" | grep -Eiv ';[[:space:]]*secure([;[:space:]]|$)' >/dev/null; then
  echo "Release smoke test failed: at least one response cookie is missing the Secure flag." >&2
  exit 1
fi

vite_css_asset="$(php -r '$manifest = json_decode(file_get_contents("public/build/manifest.json"), true, 512, JSON_THROW_ON_ERROR); $entry = $manifest["resources/js/app.js"] ?? null; if (! is_array($entry) || empty($entry["css"][0])) { exit(1); } echo "build/".$entry["css"][0];')"
: > "$headers_file"
curl --fail --silent --show-error --max-time 20 \
  --header 'Accept-Encoding: gzip' --dump-header "$headers_file" --output /dev/null \
  "${release_health_url}/${vite_css_asset}"
if ! grep -qi '^content-encoding:[[:space:]]*gzip' "$headers_file"; then
  echo "Release smoke test failed: Vite CSS is not served with gzip compression." >&2
  exit 1
fi
if ! grep -qi '^cache-control:.*immutable' "$headers_file"; then
  echo "Release smoke test failed: fingerprinted Vite assets need immutable Cache-Control." >&2
  exit 1
fi
rm -f "$headers_file"
trap - EXIT

echo "Release completed successfully."
