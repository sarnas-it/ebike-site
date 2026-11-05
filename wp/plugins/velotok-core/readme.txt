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

Правило описания вынесено в отдельную заглушку `velotok_core_description_validation_contract()` с пометкой `mocked => true`; `velotok_core_validate_product_description()` читает код ошибки из этого правила.

Каждое правило обязательного атрибута хранится в заглушке отдельно, помечено `mocked => true` и ссылается на спеку; перечень категории возвращает `velotok_core_required_attributes_contract()`.

Правило цены вынесено в отдельную заглушку `velotok_core_price_validation_contract()` с пометкой `mocked => true`; `velotok_core_validate_product_price()` читает коды ошибок из этого правила, разрешает ноль (`allow_zero => true`) и запрещает отрицательные значения (`allow_negative => false`).

Запуск контрактных тестов валидации:

```bash
php wp/plugins/velotok-core/tests/product-validation-contract-test.php
node wp/plugins/velotok-core/tests/product-validation-contract.test.js
php wp/plugins/velotok-core/tests/product-required-attributes-contract-test.php
node wp/plugins/velotok-core/tests/product-required-attributes-contract.test.js
php wp/plugins/velotok-core/tests/product-description-validation-contract-test.php
node wp/plugins/velotok-core/tests/product-description-validation-contract.test.js
php wp/plugins/velotok-core/tests/product-price-validation-contract-test.php
node wp/plugins/velotok-core/tests/product-price-validation-contract.test.js
```

= Дополнительные атрибуты товара (stub) =

Единая точка доступа — `velotok_core_product_extra_attributes_contract()` в `includes/product-extra-attributes.php`. Контракт помечен `mocked => true` и ссылается на спеку `clarify-2c698d44`.

Обязательные атрибуты карточки (`photo`, `title`, `description`, `price`) не входят в набор дополнительных. Дополнительные атрибуты задаются произвольно: контракт разрешает любые ключи, известные ключи получают подпись и порядок из справочника, неизвестные — человекочитаемую подпись.

Правила контракта:

* `velotok_core_get_product_extra_attributes()` возвращает готовый к выводу список `key`, `label`, `value`, `type`; принимает данные товара, slug, объект, id или явный массив атрибутов;
* пустые значения скрываются (`empty_value => hidden`), произвольные ключи выводятся (`unknown_attribute => rendered_humanized`);
* значения берутся из mocked-набора `stub_values` по slug товара; реальная БД, файлы, seller API и env не читаются;
* шаблон карточки выводит блок `.model-extra`, страница товара — `.product-extra`; при отсутствии плагина дополнительный блок просто не показывается.

Открытые вопросы об обязательных атрибутах после появления продавцов, источнике данных и фильтрации каталога перечислены в `velotok_core_extra_attributes_open_questions()`.

Запуск контрактного теста дополнительных атрибутов:

```bash
php wp/plugins/velotok-core/tests/product-extra-attributes-contract-test.php
node wp/plugins/velotok-core/tests/product-extra-attributes-contract.test.js
```

= Оформление заказа, доставка и оплата (stub) =

Единая точка доступа — `velotok_core_checkout_contract()` в `includes/checkout-payment-delivery.php`. Контракт помечен `mocked => true` и ссылается на спеку `clarify-096bb945`.

Правила контракта:

* оплата выполняется через ЮKassa (`velotok_core_payment_provider()`); реальный запрос не отправляется, `velotok_core_create_payment_intent()` возвращает mocked-намерение со статусом `pending`;
* доступны три способа доставки: `russian_post` («Почта РФ»), `cdek` («СДЭК») и `other` («Иной логист»); выбор проверяет `velotok_core_validate_delivery_method()`, идентификатор можно передать кодом или названием без учёта регистра;
* без выбранного способа доставки возвращается `DELIVERY_METHOD_REQUIRED`, для неизвестного способа — `DELIVERY_METHOD_INVALID`;
* контент требует приёмки человеком с ролью руководитель проекта (`project_manager`); без приёмки возвращается `CONTENT_ACCEPTANCE_REQUIRED`, при чужой роли — `CONTENT_ACCEPTANCE_ROLE_FORBIDDEN`;
* платёжная система принимается по коду `yookassa` или названию `ЮKassa`, остальные значения дают `PAYMENT_PROVIDER_UNSUPPORTED`;
* стоимость доставки в заглушке не рассчитывается: возвращается нулевая сумма с пометкой `mocked => true`;
* имена будущих env-переменных ЮKassa (`VELOTOK_YOOKASSA_SHOP_ID`, `VELOTOK_YOOKASSA_SECRET_KEY`, `VELOTOK_YOOKASSA_MODE`) только вычисляются, env не читается.

Нерешённые вопросы по test/prod режимам ЮKassa, API Почты РФ и СДЭК и расчёту стоимости доставки перечислены в `velotok_core_checkout_open_questions()`.

Запуск контрактного теста оформления заказа:

```bash
php wp/plugins/velotok-core/tests/checkout-payment-delivery-contract-test.php
node wp/plugins/velotok-core/tests/checkout-payment-delivery-contract.test.js
```

= Способы доставки (stub) =

Единая точка доступа — `velotok_core_delivery_options_contract()` в `includes/delivery-options.php`. Контракт помечен `mocked => true`, ссылается на спеку `clarify-a8fe76a0` и родительскую спецификацию `clarify-096bb945`.

Stub-провайдер отдаёт фиксированный список способов доставки:

* `russian_post` — «Почта РФ»;
* `cdek` — «СДЭК»;
* `other` — «Иной логист».

Каждый способ содержит тариф из тарифной сетки stub-провайдера (`velotok_core_delivery_tariffs()`): базовую стоимость, ставку за килограмм, порог бесплатной доставки и валюту. Стоимость рассчитывает `velotok_core_calculate_delivery_cost()` как базовая стоимость плюс ставка за килограмм с округлением веса вверх; при достижении порога по сумме заказа доставка бесплатная. Сетевые вызовы, env, файлы, БД и внешние сервисы не используются.

Выбранный способ и стоимость сохраняет `velotok_core_save_delivery_selection()`: для массива заказа возвращается обновлённый массив с `delivery_method`, `delivery_method_label`, `delivery_cost`, `delivery_currency`, `shipping_method`, `shipping_cost` и meta-полями; для объекта с методами WooCommerce (`update_meta_data`, `set_shipping_total`) значения записываются в объект. Неизвестный способ не сохраняется и даёт `DELIVERY_METHOD_INVALID`, пустой — `DELIVERY_METHOD_REQUIRED`.

Реальные службы доставки, правила тарификации и интеграция (создание отправления, трекинг, ошибки, формат данных) остаются в открытых вопросах `velotok_core_delivery_open_questions()`.

Запуск контрактного теста способов доставки:

```bash
php wp/plugins/velotok-core/tests/delivery-options-contract-test.php
node wp/plugins/velotok-core/tests/delivery-options-contract.test.js
```

= Тематический охват контента (stub) =

Единая точка доступа — `velotok_core_content_thematic_contract()` в `includes/content-thematic-coverage.php`. Контракт помечен `mocked => true`, ссылается на спеку `clarify-a2fd2f66` и родительскую спецификацию `ecommerce-store`.

Обязательные тематики:

* `model_overview` — «Обзор моделей в зелёной энергетике»;
* `legal_requirements` — «Требования законодательства»;
* `green_energy_news` — «Новости о зелёной энергетике».

Правила контракта:

* принадлежность определяется гибридно: явная метка `topic` в материале или автоматические признаки тематики и контекста зелёной энергетики;
* материал без тематических признаков не признаётся соответствующим категории и получает код `*_MISMATCH`;
* обзор моделей с признаками продвижения продукта отклоняется с кодом `MODEL_PROMOTION_DETECTED`;
* `velotok_core_check_content_coverage()` проверяет полноту покрытия всех трёх областей; при пропуске возвращается `CONTENT_COVERAGE_INCOMPLETE` и список `missing`;
* проверки выполняются на уровне интерфейса, не читают файлы, БД и внешние сервисы, содержательная экспертиза не входит в объём верификации.

Открытые вопросы по способу определения принадлежности, критериям качества обзора моделей, юридической достоверности и источникам новостей перечислены в `velotok_core_content_open_questions()`.

Запуск контрактного теста тематического охвата:

```bash
php wp/plugins/velotok-core/tests/content-thematic-coverage-contract-test.php
node wp/plugins/velotok-core/tests/content-thematic-coverage-contract.test.js
```

= Провижининг (stub) =

Единая точка доступа — `velotok_core_provisioning_contract()` в `includes/provisioning.php`. Контракт помечен `mocked => true`, ссылается на спеку `clarify-497c0475` и родительскую спецификацию `clarify-096bb945`.

Операция — `provision`, вызывается только через фасад `velotok_core_provision()`. Обязательное поле запроса — `resource_type`; необязательные — `resource_id`, `attributes`, `idempotency_key`. Для обратной совместимости принимаются псевдонимы (`type`, `id`, `params`, `idempotency` и другие из контракта).

Правила контракта:

* при валидном вводе заглушка возвращает ответ по контракту: `ok`, `status = provisioned`, `request_id`, `resource_id`, `idempotency_key`, `mocked => true`;
* одинаковый ввод всегда даёт одинаковые идентификаторы (`deterministic_input_hash`), состояние между запусками не сохраняется;
* сетевые вызовы не выполняются, env, файлы, БД и внешние сервисы не читаются;
* пустой ввод даёт `PROVISIONING_MISSING_PARAMETER`, неверные типы полей — `PROVISIONING_INVALID_INPUT`;
* замена заглушки на совместимого провайдера выполняется через `velotok_core_provisioning_register_provider()`, вызывающий код продолжает использовать `velotok_core_provision()`.

Имена будущих env-переменных (`VELOTOK_PROVISIONING_PROVIDER`, `VELOTOK_PROVISIONING_ENDPOINT`, `VELOTOK_PROVISIONING_TOKEN`, `VELOTOK_PROVISIONING_TIMEOUT`) только вычисляются, env не читается. Открытые вопросы о провайдере и протоколе, семантике ошибок, персистентности и нефункциональных требованиях перечислены в `velotok_core_provisioning_open_questions()`.

Запуск контрактного теста провижининга:

```bash
php wp/plugins/velotok-core/tests/provisioning-contract-test.php
node wp/plugins/velotok-core/tests/provisioning-contract.test.js
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
