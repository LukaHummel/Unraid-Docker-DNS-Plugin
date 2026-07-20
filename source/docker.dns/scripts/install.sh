#!/bin/bash
set -euo pipefail

config_dir="/boot/config/plugins/docker.dns"
mkdir -p "$config_dir"
chmod 700 "$config_dir"
php /usr/local/emhttp/plugins/docker.dns/include/cli.php init >/dev/null
/usr/local/emhttp/plugins/docker.dns/scripts/install-cron.sh
if docker info >/dev/null 2>&1; then
  /usr/local/emhttp/plugins/docker.dns/scripts/service.sh start
  php /usr/local/emhttp/plugins/docker.dns/include/cli.php sync >/dev/null 2>&1 || true
fi
