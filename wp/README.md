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
docker compose run --rm wpcli plugin list
php wp/themes/velotok/tests/catalog-render-test.php
php wp/themes/velotok/tests/catalog-display-test.php
php wp/themes/velotok/tests/catalog-all-products-test.php
php wp/plugins/velotok-core/tests/product-attributes-contract-test.php
php wp/plugins/velotok-core/tests/product-attributes-card-test.php
php wp/plugins/velotok-core/tests/payment-gateway-contract-test.php
node wp/plugins/velotok-core/tests/payment-gateway-contract.test.js
php wp/plugins/velotok-core/tests/accounts-contract-test.php
php wp/plugins/velotok-core/tests/accounts-wordpress-integration-test.php
node wp/plugins/velotok-core/tests/accounts-contract.test.js
node wp/plugins/velotok-core/tests/accounts-redgreen.test.js
node wp/plugins/velotok-core/tests/accounts-wordpress-integration.test.js
bash wp/tests/smoke.sh
```

Тест `catalog-render-test.php` проверяет карточки каталога электровелосипедов:
фото, название, описание, цену и произвольные дополнительные атрибуты товара.
Тест `catalog-display-test.php` проходит тот же путь, что и сайт: товары
WooCommerce → список моделей → шаблон каталога → карточка, и падает, если
каталог перестаёт выводить все товары или их обязательные и произвольные
атрибуты. Тест `catalog-all-products-test.php` проверяет, что каталог не
обрезается до первых семи товаров и что карточка сохраняет описание даже при
пустых полях описания у товара. Тесты `product-attributes-contract-test.php`
и `product-attributes-card-test.php` проверяют mocked-контракт источника
произвольных дополнительных атрибутов и их вывод в карточке. Тест
`payment-gateway-contract-test.php` проверяет шлюз ЮKassa для WooCommerce:
создание платежа, подпись и идемпотентность webhook, статусы заказа и полный
возврат в `refunded`; секреты читаются только из окружения или настроек.
Дымовой тест дополнительно проверяет эти элементы на реальной главной странице.

Тест аккаунтов `accounts-contract-test.php` проверяет регистрацию по email и
паролю, обязательное подтверждение email, вход существующего пользователя без
дубля, запрет доступа неподтверждённому пользователю к личному кабинету,
перенос гостевой корзины с переходом на `/checkout/` и завершение сессии
выходом. Red-green тест падает, если регистрация перестаёт требовать
подтверждения email.

Тест `accounts-wordpress-integration-test.php` проверяет реальную интеграцию:
пользователь сохраняется через `wc_create_new_customer()`, повторный email не
создаёт дубль, аккаунт остаётся `pending` до подтверждения, вход выполняется
через `wp_signon()`, выход — через `wp_logout()`, а форма регистрации
отправляется в `admin-post.php`. Его red-green версия
`accounts-wordpress-integration.test.js` падает, если при регистрации аккаунт
сразу помечается подтверждённым.

В контейнере `wordpress` уже есть WP-CLI (обёртка сама добавляет `--allow-root`).

Дымовой тест поднимает отдельный compose-проект, ждёт ответ главной страницы,
проверяет активные тему и плагин и наличие товаров, затем удаляет контейнеры и
тома тестового проекта.

## Остановка и сброс

```bash
docker compose down       # остановить, данные сохранить
docker compose down -v    # остановить и удалить базу, файлы WordPress и загрузки
```

## Структура

* `wp/themes/velotok/` — тема витрины и шаблоны WooCommerce;
* `wp/plugins/velotok-core/` — поля характеристик электровелосипедов;
* `wp/plugins/velotok-core/includes/product-attributes.php` — stub источника дополнительных атрибутов;
* `wp/plugins/velotok-core/includes/payment-gateway.php` — шлюз WooCommerce и ЮKassa API v3;
* `wp/plugins/velotok-core/tests/payment-gateway-contract-test.php` — тест оплаты, webhook и возврата;
* `wp/docker/wordpress/Dockerfile` — локальный образ WordPress с WP-CLI;
* `wp/cli/setup.sh` — идемпотентная первичная настройка сайта;
* `wp/cli/wp-entrypoint.sh` — обёртка WP-CLI для контейнера `wpcli`;
* `wp/cli/seed.php` — загрузка демо-каталога;
* `wp/data/products.json` — данные товаров;
* `wp/themes/velotok/tests/catalog-render-test.php` — тест карточек каталога;
* `wp/themes/velotok/tests/catalog-display-test.php` — тест вывода всего каталога;
* `wp/themes/velotok/tests/catalog-all-products-test.php` — тест полноты каталога и описания;
* `wp/plugins/velotok-core/tests/product-attributes-contract-test.php` — тест контракта дополнительных атрибутов;
* `wp/plugins/velotok-core/tests/product-attributes-card-test.php` — тест вывода дополнительных атрибутов в карточке;
* `wp/tests/smoke.sh` — дымовой тест локального запуска.
