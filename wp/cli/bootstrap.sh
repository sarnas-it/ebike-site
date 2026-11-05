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
echo "Running storefront theme contract test:"
wp eval-file wp-content/themes/velotok/tests/theme-contract-test.php --allow-root
echo "Running theme design variants contract test:"
wp eval-file wp-content/themes/velotok/tests/theme-design-variants-contract-test.php --allow-root
echo "Running specification data contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/spec-data-contract-test.php --allow-root
echo "Running product validation contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/product-validation-contract-test.php --allow-root
echo "Running required product attributes contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/product-required-attributes-contract-test.php --allow-root
echo "Running product description validation contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/product-description-validation-contract-test.php --allow-root
echo "Running product price validation contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/product-price-validation-contract-test.php --allow-root
echo "Running product extra attributes contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/product-extra-attributes-contract-test.php --allow-root
echo "Running checkout, delivery and payment contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/checkout-payment-delivery-contract-test.php --allow-root
echo "Running delivery options contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/delivery-options-contract-test.php --allow-root
echo "Running delivery order total contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/delivery-order-total-contract-test.php --allow-root
echo "Running order delivery stub contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/order-delivery-contract-test.php --allow-root
echo "Running content thematic coverage contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/content-thematic-coverage-contract-test.php --allow-root
echo "Running content articles contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/content-articles-contract-test.php --allow-root
echo "Running product catalog contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/catalog-contract-test.php --allow-root
echo "Running provisioning stub contract test:"
wp eval-file wp-content/plugins/velotok-core/tests/provisioning-contract-test.php --allow-root
exec tail -f /dev/null
