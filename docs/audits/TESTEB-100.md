# TESTEB-100 — обязательные атрибуты карточки товара

Задача: Обязательные атрибуты это фото, название описание цена: Обязательность атрибутов карточки товара

## Решение

Контракт-заглушка реализован в `wp/plugins/velotok-core/includes/product-validation.php`:

* контракт и каждое правило атрибута помечены `mocked => true`;
* обязательные атрибуты — `photo`, `title`, `description`, `price`;
* коды ошибок: `PHOTO_REQUIRED`, `PHOTO_INVALID_FORMAT`, `TITLE_REQUIRED`,
  `TITLE_TOO_LONG`, `DESCRIPTION_REQUIRED`, `PRICE_REQUIRED`,
  `PRICE_INVALID_FORMAT`, `PRICE_NEGATIVE`;
* `getRequiredAttributes()` / `velotok_core_get_required_attributes()` возвращают
  фиксированный перечень обязательных атрибутов категории;
* публикация блокируется при невалидном атрибуте, результат содержит
  `missing_attributes`.

Конфликт с базовой веткой в `product-validation.php`, `wp/README.md` и
`readme.txt` разрешён: сохранены и правила обязательных атрибутов/описания, и
отдельная заглушка правила цены (`allow_zero => true`,
`allow_negative => false`).

Контрактные тесты существуют и проверяют наличие заглушки, метку `mocked`,
перечень атрибутов, коды ошибок и правила валидации. При удалении заглушки
тесты падают. Реальная интеграция с медиатекой и справочником категорий не
используется.

## Проверено

```bash
php wp/plugins/velotok-core/tests/product-validation-contract-test.php
php wp/plugins/velotok-core/tests/product-required-attributes-contract-test.php
php wp/plugins/velotok-core/tests/product-description-validation-contract-test.php
php wp/plugins/velotok-core/tests/product-price-validation-contract-test.php
node wp/plugins/velotok-core/tests/product-validation-contract.test.js
node wp/plugins/velotok-core/tests/product-required-attributes-contract.test.js
node wp/plugins/velotok-core/tests/product-description-validation-contract.test.js
node wp/plugins/velotok-core/tests/product-price-validation-contract.test.js
php -l для всех PHP-файлов workspace
```
