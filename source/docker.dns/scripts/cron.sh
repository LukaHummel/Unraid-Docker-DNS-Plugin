#!/bin/bash
set -u

php /usr/local/emhttp/plugins/docker.dns/include/cli.php sync >/dev/null 2>&1

