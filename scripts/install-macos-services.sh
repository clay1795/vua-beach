#!/bin/zsh
set -euo pipefail

project_dir="${0:A:h:h}"
php_binary="${PHP_BINARY:-$(command -v php)}"
launch_agents_dir="${HOME}/Library/LaunchAgents"
user_domain="gui/$(id -u)"

if [[ ! -x "$php_binary" ]]; then
    print -u2 "Không tìm thấy PHP có thể thực thi: $php_binary"
    exit 1
fi

mkdir -p "$launch_agents_dir" "$project_dir/storage/logs"
chmod 750 "$project_dir/storage/logs"
find "$project_dir/storage/logs" -maxdepth 1 -type f -name '*.log*' -exec chmod 640 {} \;

for service in queue scheduler; do
    label="com.vuabeach.${service}"
    template="$project_dir/deploy/launchd/${label}.plist.template"
    target="$launch_agents_dir/${label}.plist"
    escaped_project=${project_dir//&/&amp;}
    escaped_php=${php_binary//&/&amp;}

    sed -e "s|__PROJECT_PATH__|$escaped_project|g" -e "s|__PHP_BINARY__|$escaped_php|g" "$template" > "$target"
    chmod 600 "$target"
    plutil -lint "$target" >/dev/null
    launchctl bootout "$user_domain/$label" 2>/dev/null || true
    loaded=false
    for attempt in 1 2 3; do
        if launchctl bootstrap "$user_domain" "$target" 2>/dev/null; then
            loaded=true
            break
        fi
        sleep 1
    done
    if [[ "$loaded" != true ]]; then
        print -u2 "Không thể nạp lại service $label sau 3 lần thử."
        exit 1
    fi
    launchctl enable "$user_domain/$label"
    launchctl kickstart -k "$user_domain/$label"
done

print "Đã cài queue worker và scheduler tự khởi động bằng launchd."
launchctl print "$user_domain/com.vuabeach.queue" | grep -E 'state =|pid =|last exit code' || true
launchctl print "$user_domain/com.vuabeach.scheduler" | grep -E 'state =|pid =|last exit code' || true
