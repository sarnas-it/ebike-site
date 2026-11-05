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
docker compose run --rm --entrypoint wp wpcli --allow-root eval-file wp-content/themes/velotok/tests/theme-design-variants-rejected-contract-test.php
docker compose run --rm --entrypoint wp wpcli --allow-root eval-file wp-content/plugins/velotok-core/tests/content-approval-contract-test.php
docker compose run --rm --entrypoint wp wpcli --allow-root eval-file wp-content/plugins/velotok-core/tests/content-approval-wordpress-test.php
bash wp/tests/smoke.sh
php wp/tests/theme-variants-test.php
node wp/tests/theme-variants.test.js
php wp/themes/velotok/tests/theme-contract-test.php
php wp/themes/velotok/tests/theme-design-variants-contract-test.php
php wp/themes/velotok/tests/theme-design-variants-rejected-contract-test.php
php wp/themes/velotok/tests/catalog-display-test.php
php wp/themes/velotok/tests/product-card-required-attributes-render-test.php
php wp/plugins/velotok-core/tests/spec-data-contract-test.php
php wp/plugins/velotok-core/tests/product-validation-contract-test.php
php wp/plugins/velotok-core/tests/product-required-attributes-contract-test.php
php wp/plugins/velotok-core/tests/product-card-required-attributes-contract-test.php
php wp/plugins/velotok-core/tests/product-description-validation-contract-test.php
php wp/plugins/velotok-core/tests/product-price-validation-contract-test.php
php wp/plugins/velotok-core/tests/product-extra-attributes-contract-test.php
php wp/themes/velotok/tests/extra-attributes-card-test.php
php wp/plugins/velotok-core/tests/checkout-payment-delivery-contract-test.php
php wp/plugins/velotok-core/tests/payment-integration-contract-test.php
php wp/plugins/velotok-core/tests/delivery-options-contract-test.php
php wp/plugins/velotok-core/tests/delivery-order-total-contract-test.php
php wp/plugins/velotok-core/tests/order-delivery-contract-test.php
php wp/plugins/velotok-core/tests/delivery-order-persistence-contract-test.php
php wp/plugins/velotok-core/tests/content-thematic-coverage-contract-test.php
php wp/plugins/velotok-core/tests/legal-requirements-contract-test.php
php wp/plugins/velotok-core/tests/green-energy-news-contract-test.php
php wp/plugins/velotok-core/tests/content-articles-contract-test.php
php wp/plugins/velotok-core/tests/content-approval-contract-test.php
php wp/plugins/velotok-core/tests/catalog-contract-test.php
php wp/plugins/velotok-core/tests/cart-contract-test.php
php wp/plugins/velotok-core/tests/guest-cart-registration-contract-test.php
php wp/plugins/velotok-core/tests/guest-cart-checkout-redirect-contract-test.php
php wp/plugins/velotok-core/tests/provisioning-contract-test.php
php wp/plugins/pvs/tests/pvs-contract-test.php
php wp/plugins/pvs/tests/pvs-all-plugins-contract-test.php
php wp/plugins/pvs/tests/pvs-risk-highlighting-contract-test.php
php wp/plugins/pvs/tests/pvs-providers-manual-contract-test.php
node wp/themes/velotok/tests/theme-design-variants-contract.test.js
node wp/themes/velotok/tests/theme-design-variants-rejected-redgreen.test.js
node wp/plugins/velotok-core/tests/spec-data-contract.test.js
node wp/plugins/velotok-core/tests/product-validation-contract.test.js
node wp/plugins/velotok-core/tests/product-required-attributes-contract.test.js
node wp/plugins/velotok-core/tests/product-card-required-attributes-contract.test.js
node wp/plugins/velotok-core/tests/product-description-validation-contract.test.js
node wp/plugins/velotok-core/tests/product-price-validation-contract.test.js
node wp/plugins/velotok-core/tests/product-extra-attributes-contract.test.js
node wp/plugins/velotok-core/tests/checkout-payment-delivery-contract.test.js
node wp/plugins/velotok-core/tests/payment-integration-contract.test.js
node wp/plugins/velotok-core/tests/delivery-options-contract.test.js
node wp/plugins/velotok-core/tests/delivery-order-total-contract.test.js
node wp/plugins/velotok-core/tests/order-delivery-contract.test.js
node wp/plugins/velotok-core/tests/delivery-order-persistence-contract.test.js
node wp/plugins/velotok-core/tests/content-thematic-coverage-contract.test.js
node wp/plugins/velotok-core/tests/legal-requirements-redgreen.test.js
node wp/plugins/velotok-core/tests/green-energy-news-redgreen.test.js
node wp/plugins/velotok-core/tests/content-articles-contract.test.js
node wp/plugins/velotok-core/tests/content-approval-redgreen.test.js
node wp/plugins/velotok-core/tests/catalog-contract.test.js
node wp/plugins/velotok-core/tests/cart-contract.test.js
node wp/plugins/velotok-core/tests/guest-cart-registration-contract.test.js
node wp/plugins/velotok-core/tests/guest-cart-registration-redgreen.test.js
node wp/plugins/velotok-core/tests/guest-cart-checkout-redirect-redgreen.test.js
node wp/plugins/velotok-core/tests/provisioning-contract.test.js
node wp/plugins/pvs/tests/pvs-contract.test.js
node wp/plugins/pvs/tests/pvs-risk-highlighting-redgreen.test.js
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

## Проверка плагинов на уязвимости

Плагин `wp/plugins/pvs/` проверяет все установленные плагины, включая
неактивные, mu-плагины и drop-ins. Единая точка доступа — `pvs_contract()` в
`wp/plugins/pvs/includes/class-pvs-contract.php`; запуск проверки —
`pvs_scan_all_plugins()` / `PVS_Scanner::scan()`. Контракт и отчёт помечены
`mocked => true`, `source => stub`, `network => false`, `all_plugins => true`.
Поддерживаются источники WPScan, Patchstack и ручной аудит
(`pvs_contract()['providers']`); провайдер выбирается опцией `provider`, а
находки ручного аудита передаются в `manual_findings` и подсвечиваются так же,
как остальные риски. Страница «Инструменты → Проверка плагинов» защищена nonce
и правом `manage_options`. Риски подсвечиваются по важности (`critical`, `high`,
`medium`, `low`) в отчёте и таблице плагинов по формату
`pvs_contract()['highlight_format']`; CSS подключается в админке. Реальная сеть,
env, файлы и БД не читаются.

## Структура

* `wp/themes/velotok/` — тема витрины и шаблоны WooCommerce;
* `wp/themes/velotok-bold/`, `wp/themes/velotok-minimal/` — альтернативные варианты дизайна;
* `wp/themes/design-variants.json` — статусы вариантов дизайна;
* `wp/themes/velotok/inc/theme-design-variants.php` — mocked-контракт выбора варианта дизайна темы;
* `wp/plugins/velotok-core/` — поля характеристик электровелосипедов;
* `wp/plugins/pvs/` — mocked-проверка всех плагинов на уязвимости;
* `wp/plugins/velotok-core/includes/legal-requirements.php` — mocked-контракт требований законодательства для зелёной энергетики (описание требования и ссылка на источник);
* `wp/plugins/velotok-core/includes/green-energy-news.php` — mocked-контракт новостей о зелёной энергетике (описание события и дата события);
* `wp/plugins/velotok-core/includes/content-articles.php` — mocked-контракт контентных статей о товарах и магазине;
* `wp/plugins/velotok-core/includes/content-approval.php` — обязательная приёмка контента человеком с ролью руководителя проекта (TESTEB-68);
* `wp/plugins/velotok-core/includes/catalog.php` — mocked-контракт каталога электровелосипедов;
* `wp/plugins/velotok-core/includes/cart.php` — mocked-контракт корзины для оформления заказа;
* `wp/plugins/velotok-core/includes/guest-cart-registration.php` — mocked-контракт гостевой корзины, регистрации при нажатии «Купить», переноса корзины и перехода к оформлению;
* `wp/plugins/velotok-core/includes/guest-cart-checkout-redirect.php` — отдельная точка доступа к mocked-переходу к оформлению после переноса корзины (TESTEB-81);
* `wp/plugins/velotok-core/includes/payment-integration.php` — mocked-контракт интеграции с платёжной системой для оплаты заказов;
* `wp/docker/wordpress/Dockerfile` — локальный образ WordPress с WP-CLI;
* `wp/cli/setup.sh` — идемпотентная первичная настройка сайта;
* `wp/cli/bootstrap.sh` — подготовка сайта сервисом `wpcli` при `docker compose up`;
* `wp/cli/theme-variants.php` — выбор варианта дизайна;
* `wp/cli/wp-entrypoint.sh` — обёртка WP-CLI для контейнера `wpcli`;
* `wp/cli/seed.php` — загрузка демо-каталога;
* `wp/data/products.json` — данные товаров;
* `wp/tests/theme-variants-test.php` — контракт вариантов дизайна;
* `wp/tests/smoke.sh` — дымовой тест локального запуска.
