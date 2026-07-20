#!/bin/bash
set -euo pipefail

runtime_dir="/var/run/docker.dns"
pid_file="$runtime_dir/watcher.pid"
watcher="/usr/local/emhttp/plugins/docker.dns/scripts/watch.sh"

is_watcher() {
  local candidate="${1:-}"
  [[ "$candidate" =~ ^[0-9]+$ ]] || return 1
  [[ -r "/proc/$candidate/cmdline" ]] || return 1
  tr '\0' ' ' < "/proc/$candidate/cmdline" | grep -Fq "$watcher"
}

start_service() {
  mkdir -p "$runtime_dir"
  if [[ -s "$pid_file" ]]; then
    old_pid="$(<"$pid_file")"
    if is_watcher "$old_pid" && kill -0 "$old_pid" 2>/dev/null; then
      return 0
    fi
  fi
  nohup setsid "$watcher" >/var/log/docker.dns-watcher.log 2>&1 &
  echo "$!" > "$pid_file"
}

stop_service() {
  if [[ -s "$pid_file" ]]; then
    old_pid="$(<"$pid_file")"
    if is_watcher "$old_pid"; then
      kill -- "-$old_pid" 2>/dev/null || kill "$old_pid" 2>/dev/null || true
      for _attempt in {1..20}; do
        kill -0 "$old_pid" 2>/dev/null || break
        sleep 0.1
      done
    fi
    rm -f "$pid_file"
  fi
}

case "${1:-}" in
  start) start_service ;;
  stop) stop_service ;;
  restart) stop_service; start_service ;;
  *) echo "Usage: $0 {start|stop|restart}" >&2; exit 2 ;;
esac
