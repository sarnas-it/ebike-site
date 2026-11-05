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

= Валидация карточки товара (stub) =

Единая точка доступа — `velotok_core_product_validation_contract()` в `includes/product-validation.php`. Контракт помечен `mocked => true` и ссылается на спеку `clarify-9e1a1acd`.

Обязательные атрибуты карточки: `photo`, `title`, `description`, `price`. Перечень обязательных атрибутов категории возвращает `velotok_core_get_required_attributes()` (краткий алиас — `getRequiredAttributes()`).

Правила валидации:

* название — `TITLE_REQUIRED` для пустой строки и строки из пробелов, `TITLE_TOO_LONG` при превышении предельной длины;
* описание — `DESCRIPTION_REQUIRED` для пустой строки и строки из пробелов;
* цена — `PRICE_INVALID_FORMAT` для нечислового значения, `PRICE_NEGATIVE` для отрицательного, ноль и положительные числа валидны;
* фото — `PHOTO_REQUIRED` без идентификатора, `PHOTO_INVALID_FORMAT` для MIME-типа вне конфигурации поддерживаемых форматов;
* публикация блокируется, если хотя бы один обязательный атрибут отсутствует; результат содержит `missing_attributes`.

Валидаторы не читают базу данных, файлы, медиатеку и внешние сервисы. Реальная интеграция с хранилищем изображений и справочником категорий не требуется.

Запуск контрактного теста валидации:

```bash
php wp/plugins/velotok-core/tests/product-validation-contract-test.php
node wp/plugins/velotok-core/tests/product-validation-contract.test.js
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
