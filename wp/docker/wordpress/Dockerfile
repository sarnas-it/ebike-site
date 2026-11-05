# Локальный образ WordPress с WP-CLI внутри, чтобы команды `wp plugin list`
# и `wp theme list` можно было выполнять прямо в контейнере сайта.
FROM wordpress:cli-php8.2 AS wpcli

FROM wordpress:php8.2-apache

COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp-cli.phar

RUN printf '%s\n' \
		'#!/bin/sh' \
		'exec php /usr/local/bin/wp-cli.phar --allow-root --path="${WP_PATH:-/var/www/html}" "$@"' \
		> /usr/local/bin/wp \
	&& chmod +x /usr/local/bin/wp /usr/local/bin/wp-cli.phar
