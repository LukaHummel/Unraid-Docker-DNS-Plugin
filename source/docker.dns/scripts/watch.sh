#!/bin/bash
set -u

plugin_cli="/usr/local/emhttp/plugins/docker.dns/include/cli.php"
timer_pid=""

cleanup() {
  if [[ -n "$timer_pid" ]]; then
    kill "$timer_pid" 2>/dev/null || true
    wait "$timer_pid" 2>/dev/null || true
  fi
}
trap cleanup EXIT INT TERM

while IFS= read -r event; do
  action="${event%% *}"
  case "$action" in
    create|start|stop|die|destroy|rename)
      if [[ -n "$timer_pid" ]]; then
        kill "$timer_pid" 2>/dev/null || true
        wait "$timer_pid" 2>/dev/null || true
      fi
      (
        sleep 3
        php "$plugin_cli" sync >/dev/null 2>&1
      ) &
      timer_pid="$!"
      ;;
  esac
done < <(docker events --filter type=container --format '{{.Action}} {{.ID}}')

