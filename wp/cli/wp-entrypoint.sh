#!/bin/sh
#
# Обёртка WP-CLI для локального контейнера: разрешает запуск от root и
# принимает как `wp plugin list`, так и `plugin list`.
#
set -eu

if [ "$#" -eq 0 ]; then
	set -- --info
fi

if [ "$1" = "wp" ]; then
	shift
fi

exec wp --allow-root --path="${WP_PATH:-/var/www/html}" "$@"
