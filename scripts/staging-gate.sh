#!/usr/bin/env bash

set -euo pipefail

if [[ ! -f artisan || ! -f .env ]]; then
  echo "Run this script from the staging project root with a configured .env." >&2
  exit 1
fi

for required_command in php composer npm curl; do
  if ! command -v "$required_command" >/dev/null 2>&1; then
    echo "Refusing to run: required command '$required_command' is not installed or not in PATH." >&2
    exit 1
  fi
done

bootstrap_value() {
  local expression="$1"

  php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo '"$expression"';'
}

environment="$(bootstrap_value '$app->environment()')"
if [[ "$environment" != "staging" ]]; then
  echo "Refusing to run: APP_ENV must be staging (detected: ${environment:-unknown})." >&2
  exit 1
fi

app_url="$(bootstrap_value 'config("app.url")')"
if [[ "$app_url" != https://* ]]; then
  echo "Refusing to run: staging APP_URL must use HTTPS." >&2
  exit 1
fi

database_name="$(bootstrap_value 'Illuminate\Support\Facades\DB::connection()->getDatabaseName()')"
staging_database_guard="$(bootstrap_value 'config("deployment.staging_database_guard")')"
if [[ -z "$staging_database_guard" || "$database_name" != "$staging_database_guard" || ! "$database_name" =~ (staging|stage|uat|preprod) ]]; then
  echo "Refusing to run: STAGING_DATABASE_GUARD must exactly match the live database name and include staging/stage/uat/preprod." >&2
  exit 1
fi

if ! php artisan list --raw | awk '{print $1}' | grep -qx 'test'; then
  echo "Refusing to run: staging gate needs Composer development dependencies (the Artisan test command is missing)." >&2
  exit 1
fi

# Keep staging credentials out of command arguments and shell history. When the
# staging site is protected by HTTP Basic Auth, point STAGING_CURL_NETRC at an
# absolute, regular file owned by the release user with mode 400 or 600.
curl_auth_args=()
if [[ -n "${STAGING_CURL_NETRC:-}" ]]; then
  if [[ "$STAGING_CURL_NETRC" != /* || ! -f "$STAGING_CURL_NETRC" || -L "$STAGING_CURL_NETRC" ]]; then
    echo "Refusing to run: STAGING_CURL_NETRC must be an absolute path to a regular, non-symlink file." >&2
    exit 1
  fi

  netrc_mode="$(stat -c '%a' "$STAGING_CURL_NETRC")"
  if [[ "$netrc_mode" != "400" && "$netrc_mode" != "600" ]]; then
    echo "Refusing to run: STAGING_CURL_NETRC must have mode 400 or 600 (detected: $netrc_mode)." >&2
    exit 1
  fi

  netrc_owner_uid="$(stat -c '%u' "$STAGING_CURL_NETRC")"
  if [[ "$netrc_owner_uid" != "$(id -u)" ]]; then
    echo "Refusing to run: STAGING_CURL_NETRC must be owned by the release user." >&2
    exit 1
  fi

  curl_auth_args+=(--netrc-file "$STAGING_CURL_NETRC")
fi

composer validate --no-check-publish --no-interaction
composer audit --locked --no-interaction
npm ci
npm audit --audit-level=high
npm run build
npm run test:js
php artisan optimize:clear
php artisan migrate:status
php artisan test
npm run test:e2e -- --retries=0
# These use the real staging MySQL connection, not PHPUnit/E2E's disposable
# SQLite database. A release cannot pass on mocked concurrency or an unreadable
# backup alone.
php artisan inventory:concurrency-test --workers=20 --stock=5
php artisan db:backup --keep=14
php artisan db:restore-test
php artisan vua-beach:preflight --strict
php artisan vua-beach:alert-test
if php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(config("sentry.dsn") ? 0 : 1);'; then
  php artisan sentry:test
else
  php artisan vua-beach:incident-test
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache
if [[ -f bootstrap/cache/config.php ]]; then
  runtime_group="${APP_RUNTIME_GROUP:-www-data}"
  if getent group "$runtime_group" >/dev/null 2>&1; then
    chgrp "$runtime_group" bootstrap/cache/*.php
  fi
  chmod 640 bootstrap/cache/config.php
fi

health_ready=false
for ((attempt=1; attempt<=12; attempt++)); do
  if curl "${curl_auth_args[@]}" --fail --silent --show-error --max-time 20 --output /dev/null "${app_url%/}/health"; then
    health_ready=true
    break
  fi
  sleep 10
done
if [[ "$health_ready" != true ]]; then
  echo "Staging gate failed: /health did not become ready within 120 seconds." >&2
  exit 1
fi

for public_path in / /dang-nhap /san-pham; do
  curl "${curl_auth_args[@]}" --fail --silent --show-error --location --max-time 20 \
    --output /dev/null "${app_url%/}${public_path}"
done

headers_file="$(mktemp)"
trap 'rm -f "$headers_file"' EXIT
curl "${curl_auth_args[@]}" --fail --silent --show-error --location --max-time 20 \
  --dump-header "$headers_file" --output /dev/null "${app_url%/}/dang-nhap"
if ! grep -qi '^strict-transport-security:' "$headers_file"; then
  echo "Staging gate failed: HTTPS response is missing Strict-Transport-Security." >&2
  exit 1
fi
cookie_headers="$(grep -i '^set-cookie:' "$headers_file" || true)"
if [[ -z "$cookie_headers" ]]; then
  echo "Staging gate failed: login response did not issue a session cookie." >&2
  exit 1
fi
if printf '%s\n' "$cookie_headers" | grep -Eiv ';[[:space:]]*secure([;[:space:]]|$)' >/dev/null; then
  echo "Staging gate failed: at least one response cookie is missing the Secure flag." >&2
  exit 1
fi

vite_css_asset="$(php -r '$manifest = json_decode(file_get_contents("public/build/manifest.json"), true, 512, JSON_THROW_ON_ERROR); $entry = $manifest["resources/js/app.js"] ?? null; if (! is_array($entry) || empty($entry["css"][0])) { exit(1); } echo "build/".$entry["css"][0];')"
: > "$headers_file"
curl "${curl_auth_args[@]}" --fail --silent --show-error --max-time 20 \
  --header 'Accept-Encoding: gzip' --dump-header "$headers_file" --output /dev/null \
  "${app_url%/}/${vite_css_asset}"
if ! grep -qi '^content-encoding:[[:space:]]*gzip' "$headers_file"; then
  echo "Staging gate failed: Vite CSS is not served with gzip compression." >&2
  exit 1
fi
if ! grep -qi '^cache-control:.*immutable' "$headers_file"; then
  echo "Staging gate failed: fingerprinted Vite assets need immutable Cache-Control." >&2
  exit 1
fi
rm -f "$headers_file"
trap - EXIT

echo "Staging gate passed: tests, build, audits, preflight, HTTPS and compressed immutable assets are green."
