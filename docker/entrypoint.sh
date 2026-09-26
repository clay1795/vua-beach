#!/usr/bin/env sh
set -eu

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

is_true() {
    case "${1:-}" in
        1|true|TRUE|yes|YES|on|ON) return 0 ;;
        *) return 1 ;;
    esac
}

: "${APP_KEY:?APP_KEY chưa được cấu hình}"
: "${APP_URL:?APP_URL chưa được cấu hình}"

case "$APP_URL" in
    https://*) ;;
    *) echo "APP_URL phải là URL HTTPS công khai." >&2; exit 1 ;;
esac

PORT="${PORT:-10000}"
case "$PORT" in
    ''|*[!0-9]*) echo "PORT phải là số nguyên." >&2; exit 1 ;;
esac
export PORT

if [ -n "${MYSQL_ATTR_SSL_CA:-}" ]; then
    ca_source="$MYSQL_ATTR_SSL_CA"
    [ -f "$ca_source" ] || { echo "Không tìm thấy CA MySQL tại $ca_source" >&2; exit 1; }
    mkdir -p /run/app-certificates
    chown root:www-data /run/app-certificates
    chmod 750 /run/app-certificates
    cp "$ca_source" /run/app-certificates/mysql-ca.pem
    chown www-data:www-data /run/app-certificates/mysql-ca.pem
    chmod 400 /run/app-certificates/mysql-ca.pem
    export MYSQL_ATTR_SSL_CA=/run/app-certificates/mysql-ca.pem
    php docker/check-ca.php "$MYSQL_ATTR_SSL_CA"
fi

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache

if is_true "${RUN_MIGRATIONS:-false}"; then
    php artisan migrate --force
fi

if is_true "${RUN_SEEDERS:-false}"; then
    php artisan db:seed --force
fi

[ -L public/storage ] || php artisan storage:link
php artisan route:cache
php artisan view:cache

nginx -t
php-fpm -t

php-fpm -F &
fpm_pid=$!
php artisan schedule:work &
scheduler_pid=$!
nginx -g 'daemon off;' &
nginx_pid=$!

stop_services() {
    kill "$nginx_pid" "$scheduler_pid" "$fpm_pid" 2>/dev/null || true
    wait "$nginx_pid" "$scheduler_pid" "$fpm_pid" 2>/dev/null || true
}
trap stop_services INT TERM EXIT

while kill -0 "$nginx_pid" 2>/dev/null \
    && kill -0 "$scheduler_pid" 2>/dev/null \
    && kill -0 "$fpm_pid" 2>/dev/null; do
    sleep 1
done

echo "Một tiến trình dịch vụ đã dừng; kết thúc container." >&2
exit 1
