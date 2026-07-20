#!/bin/bash
set -euo pipefail

config_dir="/boot/config/plugins/docker.dns"
source_cron="/usr/local/emhttp/plugins/docker.dns/docker.dns.cron"
mkdir -p "$config_dir"
chmod 700 "$config_dir"
cp "$source_cron" "$config_dir/docker.dns.cron"
chmod 600 "$config_dir/docker.dns.cron"
/usr/local/sbin/update_cron >/dev/null 2>&1 || true

