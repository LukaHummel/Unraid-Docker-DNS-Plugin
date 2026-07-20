#!/bin/bash
set -u

plugin_root="/usr/local/emhttp/plugins/docker.dns"
"$plugin_root/scripts/service.sh" stop 2>/dev/null || true
php "$plugin_root/include/cli.php" cleanup >/dev/null 2>&1 || logger -t docker.dns "Best-effort DNS cleanup failed during uninstall"
rm -f /boot/config/plugins/docker.dns/docker.dns.cron
/usr/local/sbin/update_cron >/dev/null 2>&1 || true

