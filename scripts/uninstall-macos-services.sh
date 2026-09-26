#!/bin/zsh
set -euo pipefail

launch_agents_dir="${HOME}/Library/LaunchAgents"
user_domain="gui/$(id -u)"

for service in queue scheduler; do
    label="com.vuabeach.${service}"
    launchctl bootout "$user_domain/$label" 2>/dev/null || true
    rm -f "$launch_agents_dir/${label}.plist"
done

print "Đã gỡ dịch vụ launchd của Vua Beach."
