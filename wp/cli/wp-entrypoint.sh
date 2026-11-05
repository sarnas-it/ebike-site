#!/bin/sh
#
# Обёртка WP-CLI для контейнера wpcli: поддерживает как `wp plugin list`,
# так и `plugin list`, а команда bootstrap запускает первичную настройку.
#
set -eu

if [ "${1:-}" = "bootstrap" ]; then
	shift
	exec /bin/sh /opt/velotok/cli/bootstrap.sh
fi

if [ "$#" -eq 0 ]; then
	set -- --info
fi

if [ "$1" = "wp" ]; then
	shift
fi

exec wp --allow-root --path="${WP_PATH:-/var/www/html}" "$@"
