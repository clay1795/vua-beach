#!/bin/zsh
set -euo pipefail

cd "${0:A:h:h}"
for executable in php nc curl ngrok; do
    command -v "$executable" >/dev/null || { print -u2 "Thiếu $executable."; exit 1; }
done
if [[ "${1:-}" == '--check' ]]; then
    print 'Đủ công cụ khởi động localhost.'
    exit 0
fi

if ! nc -z 127.0.0.1 3306 2>/dev/null; then
    sudo /Applications/XAMPP/xamppfiles/xampp startmysql
    for attempt in {1..15}; do
        nc -z 127.0.0.1 3306 2>/dev/null && break
        sleep 1
    done
    nc -z 127.0.0.1 3306 2>/dev/null || { print -u2 'MySQL chưa khởi động được.'; exit 1; }
fi

php artisan config:clear
user_domain="gui/$(id -u)"
if launchctl print "$user_domain/com.vuabeach.queue" >/dev/null 2>&1 && launchctl print "$user_domain/com.vuabeach.scheduler" >/dev/null 2>&1; then
    launchctl kickstart "$user_domain/com.vuabeach.queue"
    launchctl kickstart "$user_domain/com.vuabeach.scheduler"
else
    zsh scripts/install-macos-services.sh
fi

if ! nc -z 127.0.0.1 8000 2>/dev/null; then
    umask 077
    nohup php artisan serve --host=127.0.0.1 --port=8000 --no-reload >> storage/logs/local-web.log 2>&1 < /dev/null &!
    for attempt in {1..10}; do
        nc -z 127.0.0.1 8000 2>/dev/null && break
        sleep 1
    done
fi
curl --fail --silent --max-time 10 http://127.0.0.1:8000/ >/dev/null || { print -u2 'Web chưa sẵn sàng. Kiểm tra storage/logs/local-web.log và laravel.log.'; exit 1; }
open http://localhost:8000
print 'MySQL, web, queue và scheduler đã được bật. Ngrok sẽ công khai web để nhận callback MoMo.'
if pgrep -x ngrok >/dev/null; then
    print 'Ngrok đang chạy: không bật trùng. Kiểm tra URL và cổng chuyển tiếp tại http://127.0.0.1:4040.'
else
    print 'Giữ Terminal này mở để ngrok tiếp tục hoạt động. Ctrl+C chỉ tắt ngrok.'
    exec ngrok http --url=amani-reserved-cateringly.ngrok-free.dev 8000
fi
