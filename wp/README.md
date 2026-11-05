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
bash wp/tests/smoke.sh
php wp/plugins/velotok-core/tests/spec-data-contract-test.php
php wp/plugins/velotok-core/tests/product-validation-contract-test.php
php wp/plugins/velotok-core/tests/checkout-contracts-test.php
```

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
* `wp/plugins/velotok-core/` — поля характеристик электровелосипедов и mocked-контракты checkout, доставки и оплаты;
* `wp/docker/wordpress/Dockerfile` — локальный образ WordPress с WP-CLI;
* `wp/cli/setup.sh` — идемпотентная первичная настройка сайта;
* `wp/cli/wp-entrypoint.sh` — обёртка WP-CLI для контейнера `wpcli`;
* `wp/cli/seed.php` — загрузка демо-каталога;
* `wp/data/products.json` — данные товаров;
* `wp/tests/smoke.sh` — дымовой тест локального запуска.
