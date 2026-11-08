=== Велоток: поля товара ===
Contributors: velotok
Tags: woocommerce, product, meta, bicycle
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Характеристики электровелосипедов для карточек товаров WooCommerce.

== Description ==

Мини-плагин добавляет к товарам WooCommerce поля контракта темы «Велоток»:

* `_velotok_tagline` — короткий слоган, строка;
* `_velotok_range_km` — запас хода в километрах, целое число от 0 до 500;
* `_velotok_motor_w` — мощность мотора в ваттах, целое число от 0 до 3000;
* `_velotok_weight_kg` — вес в килограммах, число с точкой от 0 до 500;
* `_velotok_wheel` — размер колёс, строка;
* `_velotok_badge` — бейдж (например, «Хит»), строка.

Поля доступны в редакторе товара в блоке «Характеристики велосипеда», а также через REST API. Значения санитизируются, запись защищена nonce и проверкой прав на редактирование товара.

Плагин не изменяет ядро WordPress, WooCommerce или другие плагины. При деактивации и удалении данные товаров сохраняются.

= Данные спецификации (stub) =

Единая точка доступа — `velotok_core_spec_data()` / `velotok_core_spec_get()` в `includes/spec-data.php`. Контракт помечен `mocked => true` и ссылается на спеку `clarify-a1b91b62`.

Канонические ключи текущей итерации: `shop_phone`, `shop_email`, `shop_address`, `shop_hours`, `catalog_model_types`, `catalog_range`, `warranty_years`, `delivery_cities`, `installment_months`. Они перечислены в `velotok_core_spec_keys()`, значения и типы — в контракте.

Правила контракта:

* известный ключ возвращает предопределённое значение из памяти, без чтения env, файлов, БД и внешних сервисов;
* неизвестный ключ возвращает пустую строку (`missing_key => empty_string`);
* имя будущей env-переменной вычисляется как `VELOTOK_SPEC_` + ключ в верхнем регистре (например, `VELOTOK_SPEC_SHOP_PHONE`), но сама env не читается;
* типы значений объявлены в контракте; валидация и приведение типов при переходе на env выполняются в новом провайдере на границе доступа, а не в шаблонах.

Переход на реальное хранение в env считается возможным, когда: все ключи получили env-имена, потребители читают данные только через `velotok_core_spec_get()`, контрактный тест обновлён под env-провайдер и проверяет отсутствие ключа/неверный тип.

Запуск контрактного теста:

```bash
php wp/plugins/velotok-core/tests/spec-data-contract-test.php
node wp/plugins/velotok-core/tests/spec-data-contract.test.js
```

= Оплата WooCommerce и ЮKassa =

Платёжный шлюз `Velotok_Yookassa_Gateway` расширяет `WC_Payment_Gateway` и подключается через фильтр `woocommerce_payment_gateways`. Платёж создаётся через ЮKassa API v3 (`POST /payments`), для запросов используется `Idempotence-Key`.

Webhook принимает события ЮKassa на `POST /wp-json/velotok/v1/yookassa/webhook`, проверяет HMAC-SHA256 подпись из заголовка `X-Velotok-Signature` и повторно не применяет уже обработанный `event.id`. Статусы оплаты фиксированы: `pending`, `waiting_for_capture`, `succeeded`, `canceled`, `refunded`; они синхронизируются со статусами заказа WooCommerce. Полный возврат через `POST /refunds` переводит оплату и заказ в `refunded`.

Секреты не хранятся в коде: `VELOTOK_YOOKASSA_SHOP_ID`, `VELOTOK_YOOKASSA_SECRET_KEY` и `VELOTOK_YOOKASSA_WEBHOOK_SECRET` читаются из окружения или настроек WordPress.

= Аккаунты: регистрация по email, подтверждение и вход =

Единая точка доступа — `velotok_core_accounts_contract()` в `includes/accounts.php`. Контракт ссылается на спеку `accounts` и требование `TESTEB-1` и объявляет постоянное хранение пользователей (`persistent => true`, `source => 'wordpress'`).

На реальном сайте helpers из `includes/accounts.php` делегируют в `includes/accounts-wordpress.php`, который:

* регистрирует пользователя по email и паролю через `wc_create_new_customer()` (или `wp_create_user()` / `wp_insert_user()` без WooCommerce) и сохраняет его в WordPress;
* помечает новый аккаунт как `pending`, отправляет письмо с токеном подтверждения и не пускает в личный кабинет до подтверждения;
* обрабатывает ссылку подтверждения, создаёт сессию через `wp_set_auth_cookie()` и переносит корзину WooCommerce на `/checkout/`;
* входит существующим подтверждённым пользователем через `wp_signon()`; повторная регистрация существующего email не создаёт дубль и ведёт ко входу;
* завершает сессию через `wp_logout()`;
* блокирует неподтверждённый вход фильтром `authenticate` и закрывает страницу личного кабинета на `template_redirect`.

Форма регистрации доступна через shortcode `[velotok_register]`, форма входа — `[velotok_login]`; обе отправляются в `admin-post.php`. Стандартная форма регистрации WooCommerce тоже переводит покупателя в статус `pending` через hook `woocommerce_created_customer`.

In-memory реализация в `accounts.php` сохранена как fallback для изолированных тестов без WordPress.

Запуск тестов аккаунтов:

```bash
php wp/plugins/velotok-core/tests/accounts-contract-test.php
php wp/plugins/velotok-core/tests/accounts-wordpress-integration-test.php
node wp/plugins/velotok-core/tests/accounts-contract.test.js
node wp/plugins/velotok-core/tests/accounts-redgreen.test.js
node wp/plugins/velotok-core/tests/accounts-wordpress-integration.test.js
```

== Installation ==

1. Убедитесь, что установлен и активирован WooCommerce.
2. Скопируйте каталог `velotok-core` в `wp-content/plugins/`.
3. Активируйте плагин «Велоток: поля товара» в разделе «Плагины».
4. Откройте любой товар и заполните блок «Характеристики велосипеда».

== Frequently Asked Questions ==

= Что будет, если WooCommerce не активен? =

Плагин не ломает сайт и показывает администратору уведомление о необходимости активировать WooCommerce.

= Удаляются ли характеристики при удалении плагина? =

Нет. Поля товаров считаются данными клиента и сохраняются.

== Changelog ==

= 1.0.0 =
* Первая версия: поля характеристик товара, метабокс в админке, REST-доступ.
