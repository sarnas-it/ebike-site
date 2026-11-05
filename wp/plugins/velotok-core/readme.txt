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
* стоимость доставки рассчитывается по тарифной сетке заглушки (`tiers` по сумме заказа), результат помечен `mocked => true`, `source = stub`; от порога `free_from` действует бесплатная доставка;
* выбранный способ и рассчитанная стоимость сохраняются в заказе через `velotok_core_save_delivery_option()` (`delivery_method`, `delivery_method_label`, `delivery_cost`, `delivery_currency`), без записи в БД;
* имена будущих env-переменных ЮKassa (`VELOTOK_YOOKASSA_SHOP_ID`, `VELOTOK_YOOKASSA_SECRET_KEY`, `VELOTOK_YOOKASSA_MODE`) только вычисляются, env не читается.

Нерешённые вопросы по test/prod режимам ЮKassa, API Почты РФ и СДЭК и расчёту стоимости доставки перечислены в `velotok_core_checkout_open_questions()`.

Запуск контрактного теста оформления заказа:

```bash
php wp/plugins/velotok-core/tests/checkout-payment-delivery-contract-test.php
node wp/plugins/velotok-core/tests/checkout-payment-delivery-contract.test.js
```

= Способы доставки (stub) =

Единая точка доступа — `velotok_core_delivery_options_contract()` в `includes/delivery-options.php`. Контракт помечен `mocked => true`, ссылается на спеку `clarify-a8fe76a0` и родительскую спецификацию `clarify-096bb945`.

Правила контракта:

* список способов доставки поставляет stub-провайдер: `russian_post` («Почта РФ»), `cdek` («СДЭК») и `other` («Иной логист»); получить его можно через `velotok_core_delivery_options()` / `velotok_core_get_delivery_options()`;
* тарифная сетка задана для каждого способа в `tariffs` / `tariff_grid`; уровни `tiers` выбираются по сумме заказа, у каждого тарифа есть базовая стоимость `base`, ставка `per_kg`, валюта и порог бесплатной доставки `free_from`;
* `velotok_core_delivery_options_cost()` применяет тарифную сетку по сумме заказа; `velotok_core_calculate_delivery_cost()` рассчитывает стоимость как `base + per_kg` с округлением веса вверх; оба результата помечены `mocked => true`, `source = stub`, `calculated => true`;
* выбранный способ и стоимость сохраняются в форме заказа через `velotok_core_delivery_options_apply_to_order()` / `velotok_core_save_delivery_option()` / `velotok_core_save_delivery_selection()`; реальная запись в БД не выполняется (`persisted => false`);
* заглушка не читает env, файлы, БД и не обращается к внешним сервисам; реальная интеграция со службами доставки не требуется.

Открытые вопросы о перечне реальных служб, правилах тарификации и интеграции (создание отправления, трекинг, ошибки, формат данных) перечислены в `velotok_core_delivery_options_open_questions()`.


Запуск контрактного теста способов доставки:

```bash
php wp/plugins/velotok-core/tests/delivery-options-contract-test.php
node wp/plugins/velotok-core/tests/delivery-options-contract.test.js
```

= Стоимость доставки в итоговой сумме заказа (stub) =

Единая точка доступа — `velotok_core_delivery_order_total_contract()` в `includes/delivery-order-total.php`. Контракт помечен `mocked => true`, ссылается на спеку `clarify-b0e1a6d7` и родительскую спецификацию `clarify-a8fe76a0`.

Правила контракта:

* итоговая сумма заказа всегда включает стоимость доставки: `includes_delivery => true`, `total_includes_delivery => true`;
* `velotok_core_delivery_order_total()` рассчитывает подытог и стоимость доставки через тарифную сетку stub-провайдера способов доставки, а затем возвращает итог как `subtotal + delivery_cost`;
* результат содержит строки вывода `subtotal`, `delivery`, `total` с подписями «Товары», «Доставка», «Итого» и отформатированными суммами; бесплатная доставка помечается подписью «Бесплатно»;
* стоимость доставки можно явно передать в `options['delivery_cost']` (например, уже сохранённую стоимость в заказе WooCommerce) без сетевых запросов;
* разметку для витрины возвращает `velotok_core_render_delivery_order_total()` с пометкой `data-mocked="true"`; страница «Спасибо» выводит строку доставки, если у заказа есть способ доставки;
* для неизвестного способа возвращается `DELIVERY_METHOD_INVALID` и пустая итоговая сумма; сетевые вызовы, env, файлы, БД и внешние сервисы не читаются.

Реальные службы доставки, тарифная сетка, интеграция и зависимость способов доставки от адреса и параметров заказа остаются в открытых вопросах `velotok_core_delivery_order_total_open_questions()`.

Запуск контрактного теста стоимости доставки в итоговой сумме:

```bash
php wp/plugins/velotok-core/tests/delivery-order-total-contract-test.php
node wp/plugins/velotok-core/tests/delivery-order-total-contract.test.js
```

= Оформление доставки заказа (stub) =

Единая точка доступа — `velotok_core_order_delivery_contract()` в `includes/order-delivery.php`. Контракт помечен `mocked => true`, ссылается на спеку `clarify-b0e1a6d7`, родительскую спецификацию `clarify-a8fe76a0` и спецификацию оформления заказа `clarify-096bb945`.

Правила контракта:

* при оформлении заказа доступны способы доставки stub-провайдера: `russian_post` («Почта РФ»), `cdek` («СДЭК») и `other` («Иной логист»); список отдаёт `velotok_core_order_delivery_options()`;
* выбранный способ проверяет `velotok_core_validate_order_delivery_method()`: пустой выбор даёт `DELIVERY_METHOD_REQUIRED`, неизвестный — `DELIVERY_METHOD_INVALID`;
* стоимость доставки рассчитывает `velotok_core_order_delivery_cost()` по тарифной сетке stub-провайдера; расчёт не обращается к сети, env, файлам и БД;
* `velotok_core_order_delivery_totals()` возвращает подытог, стоимость доставки и итоговую сумму заказа, в которую стоимость доставки включена (`delivery_in_total => true`); без явного выбора применяется способ по умолчанию;
* `velotok_core_save_checkout_delivery()` сохраняет способ и стоимость в полях заказа (`delivery_method`, `delivery_cost`, `shipping_method`, `shipping_cost`) и meta-полях; `velotok_core_apply_checkout_delivery()` обновляет массив заказа по ссылке;
* реальная интеграция со службами доставки не выполняется (`real_integration.required => false`); открытые вопросы о перечне служб, тарифах, протоколах и зависимости от адреса перечислены в `velotok_core_order_delivery_open_questions()`.

Запуск контрактного теста оформления доставки заказа:

```bash
php wp/plugins/velotok-core/tests/order-delivery-contract-test.php
node wp/plugins/velotok-core/tests/order-delivery-contract.test.js
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

= Каталог электровелосипедов (stub) =

Единая точка доступа — `velotok_core_catalog_contract()` в `includes/catalog.php`. Контракт помечен `mocked => true`, ссылается на спеку `ecommerce-store` и требование `TESTEB-150`.

Правила контракта:

* каталог содержит четыре обязательные категории: `city` («Городские»), `mountain` («Горные»), `cargo` («Грузовые») и `folding` («Складные»);
* демонстрационный ассортимент — семь электровелосипедов (`riviera`, `street-7`, `hrebet-pro`, `hrebet-trail`, `family`, `courier`, `sklad-20`) с полями модели, цены и характеристик;
* обязательные поля товара: `slug`, `name`, `price`, `category`; характеристики (`range_km`, `motor_w`, `weight_kg`, `wheel`) и изображение необязательны;
* список товаров отдаёт `velotok_core_catalog_products()` с фильтром по категории, лимитом, смещением и сортировкой; краткий алиас — `catalogProducts()`;
* один товар находят по slug или названию через `velotok_core_catalog_product()`; неизвестный товар возвращает `null`, неизвестная категория — пустой список;
* заглушка не читает env, файлы, БД и не обращается к WooCommerce или внешним сервисам; реальная синхронизация каталога не требуется;
* если WooCommerce ещё не установлен, тема витрины использует этот же mocked-каталог как резервный источник моделей.

Открытые вопросы об атрибутах товаров, связанных статьях, фильтрах, остатках и источнике данных перечислены в `velotok_core_catalog_open_questions()`.

Запуск контрактного теста каталога:

```bash
php wp/plugins/velotok-core/tests/catalog-contract-test.php
node wp/plugins/velotok-core/tests/catalog-contract.test.js
```

= Корзина для оформления заказа (stub) =

Единая точка доступа — `velotok_core_cart_contract()` в `includes/cart.php`. Контракт помечен `mocked => true`, ссылается на спеку `ecommerce-store` и требование `TESTEB-151`.

Правила контракта:

* корзина хранится только в памяти и не сохраняется между запросами (`storage.persistent => false`, `network => false`); WooCommerce, env, файлы и БД не читаются;
* операции: `velotok_core_cart_create()`, `velotok_core_cart_items()`, `velotok_core_cart_get_item()`, `velotok_core_cart_add_item()`, `velotok_core_cart_update_item()`, `velotok_core_cart_remove_item()`, `velotok_core_cart_clear()`;
* товар добавляется по slug, названию или данным из mocked-каталога; повторное добавление суммирует количество, сумма позиции считается как цена × количество;
* итоги возвращает `velotok_core_cart_totals()`: количество единиц, количество позиций, подытог и итог в RUB; доставка добавляется на этапе оформления заказа, а не в корзине;
* ошибочные сценарии: `CART_PRODUCT_NOT_FOUND` для неизвестного товара, `CART_ITEM_NOT_FOUND` для неизвестной позиции, `CART_QUANTITY_INVALID` для неположительного количества и `CART_EMPTY` для оформления пустой корзины;
* гостевая корзина включена (`guest_cart.enabled => true`); `velotok_core_cart_transfer_guest_cart()` переносит её при входе или регистрации со стратегией `merge_quantities`, суммируя количество одинаковых товаров;
* нерешённый сценарий гостевой корзины и её переноса при регистрации зафиксирован в `velotok_core_cart_open_questions()` (`clarify-30af92ca`), как и вопросы хранения, цен, остатков и интеграции с WooCommerce.

Запуск контрактного теста корзины:

```bash
php wp/plugins/velotok-core/tests/cart-contract-test.php
node wp/plugins/velotok-core/tests/cart-contract.test.js
```

= Гостевая корзина и перенос при регистрации (stub) =

Единая точка доступа — `velotok_core_guest_cart_registration_contract()` в `includes/guest-cart-registration.php`. Контракт помечен `mocked => true`, ссылается на спеку `clarify-30af92ca`, требования `TESTEB-78`/`TESTEB-79` (`requirement_ids`, `task => TESTEB-79`), родительская спецификация — `ecommerce-store`.

Правила контракта:

* неавторизованный пользователь собирает корзину через `velotok_core_guest_cart_create()` и `velotok_core_guest_cart_add()`; повторное добавление товара суммирует количество;
* нажатие «Купить» запускает `velotok_core_guest_cart_buy()`: сначала регистрирует пользователя по email через `velotok_core_guest_cart_register()`, затем переносит гостевую корзину в аккаунт через `velotok_core_guest_cart_transfer()`;
* при переносе количество одинаковых товаров суммируется (`merge_quantities`, `merge_by => product_id`), существующая корзина пользователя сохраняется, гостевая после переноса очищается;
* после успешного переноса `velotok_core_guest_cart_checkout_redirect()` возвращает переход `/checkout/` с целью `checkout`; пустая корзина даёт `CART_EMPTY`, а ошибка регистрации сохраняет гостевую корзину;
* заглушка не читает env, файлы, БД и не обращается к внешним сервисам; реальная регистрация, WooCommerce-аккаунт и маршрутизация оформления не выполняются (`network => false`, `persistent => false`).

Открытые вопросы о способе аутентификации, подтверждении email, обработке существующего пользователя, правилах слияния, целевой странице оформления и локальном запуске перечислены в `velotok_core_guest_cart_open_questions()`.

Запуск контрактных тестов гостевой корзины:

```bash
php wp/plugins/velotok-core/tests/guest-cart-registration-contract-test.php
node wp/plugins/velotok-core/tests/guest-cart-registration-contract.test.js
node wp/plugins/velotok-core/tests/guest-cart-registration-redgreen.test.js
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

= Интеграция с платёжной системой (stub) =

Единая точка доступа — `velotok_core_payment_integration_contract()` в `includes/payment-integration.php`. Контракт помечен `mocked => true`, ссылается на спеку `ecommerce-store` и требование `TESTEB-153`.

Правила контракта:

* платёжная система — ЮKassa (`yookassa`, «ЮKassa»); реальное списание денег не выполняется (`network => false`, `real_integration.required => false`);
* поддерживаемые статусы оплаты: `pending`, `waiting_for_capture`, `succeeded`, `canceled`; `succeeded` — финальный оплаченный статус;
* `velotok_core_payment_integration_create()` / `velotok_core_payment_integration_process()` создают mocked-платёж из суммы заказа с валютой RUB и внутренней ссылкой подтверждения `mock://yookassa/...`; платёж создаётся в статусе `pending` и не считается оплаченным;
* `velotok_core_payment_integration_status()` разрешает статус, `velotok_core_payment_integration_confirm()` применяет mocked-переход, а `velotok_core_payment_integration_is_paid()` возвращает `true` только для `succeeded`;
* `velotok_core_payment_integration_validate()` проверяет провайдера, валюту, сумму и статус; ошибки: `PAYMENT_PROVIDER_UNSUPPORTED`, `PAYMENT_AMOUNT_INVALID`, `PAYMENT_CURRENCY_UNSUPPORTED`, `PAYMENT_STATUS_INVALID`;
* имена будущих env-переменных (`VELOTOK_YOOKASSA_SHOP_ID`, `VELOTOK_YOOKASSA_SECRET_KEY`, `VELOTOK_YOOKASSA_MODE`) только вычисляются, env не читается;
* реальная интеграция не требуется; открытые вопросы о провайдере, режимах, статусах, подтверждении и синхронизации заказа перечислены в `velotok_core_payment_integration_open_questions()` (`clarify-096bb945`).

Запуск контрактного теста интеграции с платёжной системой:

```bash
php wp/plugins/velotok-core/tests/payment-integration-contract-test.php
node wp/plugins/velotok-core/tests/payment-integration-contract.test.js
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
