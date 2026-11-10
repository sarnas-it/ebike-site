#!/bin/sh
#
# Идемпотентная подготовка локального сайта «Велоток».
# Запускается сервисом setup из docker-compose.yml.
#
set -eu

WP_PATH="${WP_PATH:-/var/www/html}"
SITE_URL="${WORDPRESS_URL:-http://localhost:8080}"
ADMIN_USER="${WORDPRESS_ADMIN_USER:-admin}"
ADMIN_PASSWORD="${WORDPRESS_ADMIN_PASSWORD:-velotok-local}"
ADMIN_EMAIL="${WORDPRESS_ADMIN_EMAIL:-admin@velotok.local}"
DB_NAME="${WORDPRESS_DB_NAME:-wordpress}"
DB_USER="${WORDPRESS_DB_USER:-wordpress}"
DB_PASSWORD="${WORDPRESS_DB_PASSWORD:-wordpress}"
DB_HOST="${WORDPRESS_DB_HOST:-db:3306}"

log() {
	printf '[velotok-setup] %s\n' "$*"
}

wp_cli() {
	wp --allow-root --path="$WP_PATH" "$@"
}

wait_for_core() {
	attempt=0
	while [ ! -f "$WP_PATH/wp-includes/version.php" ]; do
		attempt=$((attempt + 1))
		if [ "$attempt" -gt 90 ]; then
			log "WordPress core files were not prepared in time"
			exit 1
		fi
		sleep 2
	done
}

ensure_config() {
	attempt=0
	while [ ! -f "$WP_PATH/wp-config.php" ]; do
		attempt=$((attempt + 1))
		if [ "$attempt" -gt 60 ]; then
			log "wp-config.php is missing, creating it from environment"
			wp_cli config create \
				--dbname="$DB_NAME" \
				--dbuser="$DB_USER" \
				--dbpass="$DB_PASSWORD" \
				--dbhost="$DB_HOST" \
				--skip-check
			return
		fi
		sleep 2
	done
}

wait_for_db() {
	attempt=0
	until wp_cli db check >/dev/null 2>&1; do
		attempt=$((attempt + 1))
		if [ "$attempt" -gt 60 ]; then
			log "Database is not ready"
			exit 1
		fi
		sleep 2
	done
}

wait_for_core
ensure_config
wait_for_db

if ! wp_cli core is-installed >/dev/null 2>&1; then
	log "Installing WordPress at $SITE_URL"
	wp_cli core install \
		--url="$SITE_URL" \
		--title="Велоток — электровелосипеды" \
		--admin_user="$ADMIN_USER" \
		--admin_password="$ADMIN_PASSWORD" \
		--admin_email="$ADMIN_EMAIL" \
		--skip-email
fi

wp_cli option update home "$SITE_URL" >/dev/null
wp_cli option update siteurl "$SITE_URL" >/dev/null
wp_cli option update blogdescription "Магазин и мастерская электровелосипедов" >/dev/null

log "Installing Russian locale"
wp_cli language core install ru_RU --activate >/dev/null 2>&1 \
	|| log "WARNING: ru_RU language pack could not be installed (offline environment?)"
wp_cli site switch-language ru_RU >/dev/null 2>&1 || true
wp_cli option update WPLANG "ru_RU" >/dev/null 2>&1 || true
wp_cli option update timezone_string "Europe/Moscow" >/dev/null 2>&1 || true
wp_cli option update date_format "d.m.Y" >/dev/null 2>&1 || true
wp_cli option update time_format "H:i" >/dev/null 2>&1 || true
wp_cli option update start_of_week "1" >/dev/null 2>&1 || true
wp_cli option update permalink_structure "/%postname%/" >/dev/null 2>&1 || true
wp_cli rewrite flush --hard >/dev/null 2>&1 || true

if ! wp_cli theme is-installed velotok; then
	log "Theme velotok is missing"
	exit 1
fi
wp_cli theme activate velotok >/dev/null

if ! wp_cli plugin is-installed woocommerce; then
	log "Installing WooCommerce"
	if ! wp_cli plugin install woocommerce --activate; then
		log "WARNING: WooCommerce could not be installed (offline environment?)"
	fi
else
	wp_cli plugin activate woocommerce >/dev/null 2>&1 || true
fi

if wp_cli plugin is-installed velotok-core; then
	if ! wp_cli plugin activate velotok-core >/dev/null 2>&1; then
		log "WARNING: velotok-core is not active (WooCommerce is required)"
	fi
fi

log "Installing Russian language packs (core and WooCommerce)"
wp_cli eval '
if ( function_exists( "velotok_localization_install_language_packs" ) ) {
	foreach ( velotok_localization_install_language_packs() as $command => $installed ) {
		WP_CLI::log( ( $installed ? "OK: " : "WARNING: " ) . $command );
	}
	foreach ( velotok_localization_language_packs_installed() as $pack => $present ) {
		WP_CLI::log( ( $present ? "OK: " : "WARNING: " ) . "ru_RU files for " . $pack . ( $present ? " are installed" : " are missing" ) );
	}
}
' || log "WARNING: Russian language packs could not be installed (offline environment?)"

log "Installing Contact Form 7 and Flamingo"
for plugin in contact-form-7 flamingo; do
	if ! wp_cli plugin is-installed "$plugin"; then
		if ! wp_cli plugin install "$plugin" --activate; then
			log "WARNING: $plugin could not be installed (offline environment?)"
		fi
	else
		wp_cli plugin activate "$plugin" >/dev/null 2>&1 || true
	fi
done

if wp_cli plugin is-active contact-form-7 >/dev/null 2>&1; then
	log "Installing Contact Form 7 and Flamingo Russian translations"
	wp_cli language plugin install contact-form-7 ru_RU >/dev/null 2>&1 \
		|| log "WARNING: Contact Form 7 ru_RU translation could not be installed (offline environment?)"
	wp_cli language plugin install flamingo ru_RU >/dev/null 2>&1 \
		|| log "WARNING: Flamingo ru_RU translation could not be installed (offline environment?)"

	log "Configuring lead forms"
	wp_cli eval-file "/opt/velotok/cli/lead-forms.php" \
		|| log "WARNING: lead forms setup failed"
else
	log "WARNING: Contact Form 7 is not active; lead forms were not created"
fi

if wp_cli plugin is-active woocommerce >/dev/null 2>&1; then
	log "Configuring WooCommerce"
	wp_cli eval 'if ( class_exists( "WC_Install" ) ) { WC_Install::create_pages(); }' >/dev/null 2>&1 || true
	wp_cli eval 'if ( function_exists( "velotok_checkout_classic_migrate_page" ) ) { velotok_checkout_classic_migrate_page(); }' >/dev/null 2>&1 || true
	wp_cli option update woocommerce_currency "RUB" >/dev/null 2>&1 || true
	wp_cli option update woocommerce_currency_symbol "₽" >/dev/null 2>&1 || true
	wp_cli option update woocommerce_price_num_decimals "0" >/dev/null 2>&1 || true
	wp_cli option update woocommerce_price_decimal_sep "," >/dev/null 2>&1 || true
	wp_cli option update woocommerce_price_thousand_sep " " >/dev/null 2>&1 || true
	wp_cli option update woocommerce_price_format '%2$s %1$s' >/dev/null 2>&1 || true
	wp_cli option update woocommerce_enable_guest_checkout "yes" >/dev/null 2>&1 || true
	wp_cli option update woocommerce_cod_settings '{"enabled":"yes","title":"Оплата при получении","description":"Оплата наличными или картой при получении заказа.","instructions":"Менеджер свяжется для подтверждения заказа и доставки."}' --format=json >/dev/null 2>&1 || true
	wp_cli option update woocommerce_default_country "RU:MOW" >/dev/null 2>&1 || true

	log "Localizing WooCommerce pages"
	wp_cli eval 'if ( function_exists( "velotok_localize_store" ) ) { velotok_localize_store(); }' >/dev/null 2>&1 || true

	log "Seeding demo catalog"
	wp_cli eval-file "/opt/velotok/cli/seed.php" \
		|| log "WARNING: demo catalog seed failed"

	log "Provisioning store: payment gateways, delivery zones and language pack"
	wp_cli eval-file "/opt/velotok/cli/provision.php" \
		|| log "WARNING: store provisioning failed"

	log "Ensuring WooCommerce delivery zones (legacy fallback)"
	wp_cli eval-file "/opt/velotok/cli/delivery-zones.php" \
		|| log "WARNING: delivery zones fallback setup failed"
fi

create_page() {
	slug="$1"
	title="$2"
	content="$3"
	if ! wp_cli post list --post_type=page --name="$slug" --field=ID | grep -q '[0-9]'; then
		wp_cli post create \
			--post_type=page \
			--post_status=publish \
			--post_title="$title" \
			--post_name="$slug" \
			--post_content="$content" >/dev/null
	fi
}

create_page "about" "О магазине" "«Велоток» — магазин и мастерская электровелосипедов. Помогаем выбрать технику под маршрут, собираем и проверяем каждую модель перед выдачей."
create_page "delivery" "Доставка и оплата" "Доставляем собранные велосипеды в жёсткой коробке. По Москве и Санкт-Петербургу — на следующий день, в остальные города — курьерской службой. Доступна оплата при получении."
create_page "warranty" "Гарантия и сервис" "Гарантия на раму и мотор — 2 года. Своя мастерская: диагностика батареи, ремонт и настройка по записи."

chmod -R a+rwX "$WP_PATH/wp-content/uploads" 2>/dev/null || true

log "Site is ready: $SITE_URL"
