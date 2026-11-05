# Локальный WordPress-магазин «Велоток»

Каталог `wp/` содержит исходники темы и плагина, которые `docker-compose.yml`
монтирует в контейнер WordPress. Сайт поднимается одной командой из корня проекта.

## Запуск

```bash
docker compose up -d
```

После старта:

* витрина — <http://localhost:8080>;
* админка — <http://localhost:8080/wp-admin/>;
* логин по умолчанию — `admin`, пароль — `velotok-local` (меняется переменными
  `WORDPRESS_ADMIN_USER`, `WORDPRESS_ADMIN_PASSWORD`, `WORDPRESS_ADMIN_EMAIL`).

Сервис `setup` идемпотентно устанавливает WordPress, активирует тему `velotok` и
плагин `velotok-core`, устанавливает WooCommerce, создаёт служебные страницы и
заполняет каталог демо-товарами из `wp/data/products.json`. Если сети нет и
WooCommerce установить не удалось, сайт всё равно открывается: тема показывает
встроенный каталог-заглушку.

## Проверки

```bash
docker compose exec wordpress wp plugin list
docker compose exec wordpress wp theme list
docker compose run --rm --entrypoint wp wpcli --allow-root plugin list
docker compose run --rm --entrypoint wp wpcli --allow-root eval-file wp-content/themes/velotok/tests/theme-contract-test.php
docker compose run --rm --entrypoint wp wpcli --allow-root eval-file wp-content/themes/velotok/tests/theme-design-variants-contract-test.php
bash wp/tests/smoke.sh
php wp/tests/theme-variants-test.php
node wp/tests/theme-variants.test.js
php wp/themes/velotok/tests/theme-contract-test.php
php wp/themes/velotok/tests/theme-design-variants-contract-test.php
php wp/themes/velotok/tests/catalog-display-test.php
php wp/plugins/velotok-core/tests/spec-data-contract-test.php
php wp/plugins/velotok-core/tests/product-validation-contract-test.php
php wp/plugins/velotok-core/tests/product-required-attributes-contract-test.php
php wp/plugins/velotok-core/tests/product-description-validation-contract-test.php
php wp/plugins/velotok-core/tests/product-price-validation-contract-test.php
php wp/plugins/velotok-core/tests/product-extra-attributes-contract-test.php
php wp/themes/velotok/tests/extra-attributes-card-test.php
php wp/plugins/velotok-core/tests/checkout-payment-delivery-contract-test.php
php wp/plugins/velotok-core/tests/delivery-options-contract-test.php
php wp/plugins/velotok-core/tests/delivery-order-total-contract-test.php
php wp/plugins/velotok-core/tests/order-delivery-contract-test.php
php wp/plugins/velotok-core/tests/content-thematic-coverage-contract-test.php
php wp/plugins/velotok-core/tests/content-articles-contract-test.php
php wp/plugins/velotok-core/tests/provisioning-contract-test.php
node wp/themes/velotok/tests/theme-design-variants-contract.test.js
node wp/plugins/velotok-core/tests/spec-data-contract.test.js
node wp/plugins/velotok-core/tests/product-validation-contract.test.js
node wp/plugins/velotok-core/tests/product-required-attributes-contract.test.js
node wp/plugins/velotok-core/tests/product-description-validation-contract.test.js
node wp/plugins/velotok-core/tests/product-price-validation-contract.test.js
node wp/plugins/velotok-core/tests/product-extra-attributes-contract.test.js
node wp/plugins/velotok-core/tests/checkout-payment-delivery-contract.test.js
node wp/plugins/velotok-core/tests/delivery-options-contract.test.js
node wp/plugins/velotok-core/tests/delivery-order-total-contract.test.js
node wp/plugins/velotok-core/tests/order-delivery-contract.test.js
node wp/plugins/velotok-core/tests/content-thematic-coverage-contract.test.js
node wp/plugins/velotok-core/tests/content-articles-contract.test.js
node wp/plugins/velotok-core/tests/provisioning-contract.test.js
```

В контейнере `wordpress` уже есть WP-CLI (обёртка сама добавляет `--allow-root`).

Дымовой тест поднимает отдельный compose-проект, ждёт ответ главной страницы,
проверяет активные тему и плагин, наличие вариантов дизайна в `wp theme list`
и наличие товаров, затем удаляет контейнеры и тома тестового проекта.

## Варианты дизайна темы

В каталоге `wp/themes/` лежат три полноценные темы WordPress:

* `velotok` — «Классика» (текущий выбранный вариант);
* `velotok-bold` — «Смелый»: контраст, неоновый акцент, крупная типографика;
* `velotok-minimal` — «Минимализм»: светлая палитра, тонкие линии, много воздуха.

Статусы «выбран / отклонён» фиксируются в манифесте
`wp/themes/design-variants.json`. Выбрать вариант и автоматически пометить
остальные отклонёнными можно командой:

```bash
php wp/cli/theme-variants.php list
php wp/cli/theme-variants.php select velotok-bold
```

После выбора активируйте тему штатно: `wp theme activate velotok-bold`.
Отклонённые варианты остаются в `wp/themes/`, чтобы к ним можно было вернуться.
Скрипт первичной настройки и `docker compose` при повторном запуске активируют
вариант, записанный в поле `selected` манифеста.

## Остановка и сброс

```bash
docker compose down       # остановить, данные сохранить
docker compose down -v    # остановить и удалить базу, файлы WordPress и загрузки
```

## Структура

* `wp/themes/velotok/` — тема витрины и шаблоны WooCommerce;
* `wp/themes/velotok-bold/`, `wp/themes/velotok-minimal/` — альтернативные варианты дизайна;
* `wp/themes/design-variants.json` — статусы вариантов дизайна;
* `wp/themes/velotok/inc/theme-design-variants.php` — mocked-контракт выбора варианта дизайна темы;
* `wp/plugins/velotok-core/` — поля характеристик электровелосипедов;
* `wp/plugins/velotok-core/includes/content-articles.php` — mocked-контракт контентных статей о товарах и магазине;
* `wp/docker/wordpress/Dockerfile` — локальный образ WordPress с WP-CLI;
* `wp/cli/setup.sh` — идемпотентная первичная настройка сайта;
* `wp/cli/bootstrap.sh` — подготовка сайта сервисом `wpcli` при `docker compose up`;
* `wp/cli/theme-variants.php` — выбор варианта дизайна;
* `wp/cli/wp-entrypoint.sh` — обёртка WP-CLI для контейнера `wpcli`;
* `wp/cli/seed.php` — загрузка демо-каталога;
* `wp/data/products.json` — данные товаров;
* `wp/tests/theme-variants-test.php` — контракт вариантов дизайна;
* `wp/tests/smoke.sh` — дымовой тест локального запуска.
