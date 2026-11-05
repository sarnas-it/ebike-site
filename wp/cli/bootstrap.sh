#!/bin/sh
#
# Идемпотентная подготовка WordPress и контрактные проверки для сервиса wpcli.
#
set -eu

until wp core is-installed --allow-root >/dev/null 2>&1; do
  wp core install \
    --url="http://localhost:8080" \
    --title="Велоток" \
    --admin_user="admin" \
    --admin_password="${WP_ADMIN_PASSWORD:-velotok-admin}" \
    --admin_email="admin@example.test" \
    --skip-email \
    --allow-root >/dev/null 2>&1 && break
  sleep 3
done
wp plugin install woocommerce --activate --allow-root >/dev/null 2>&1 || true
wp plugin activate velotok-core --allow-root >/dev/null 2>&1 || true
selected="$(php -r '$m=json_decode((string)@file_get_contents("/var/www/html/wp-content/themes/design-variants.json"), true); echo isset($m["selected"]) ? $m["selected"] : "";' 2>/dev/null || true)"
[ -n "$selected" ] || selected=velotok
wp theme activate "$selected" --allow-root >/dev/null 2>&1 || wp theme activate velotok --allow-root >/dev/null 2>&1 || true
echo "Running specification data contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/spec-data-contract-test.php --allow-root
echo "Running product validation contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/product-validation-contract-test.php --allow-root
echo "Running checkout, delivery and payment contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/checkout-payment-delivery-contract-test.php --allow-root
exec tail -f /dev/null
