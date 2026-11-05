# TESTEB-143 — невыбранные варианты дизайна отклоняются

Задача: Варианты дизайна темы: Система SHALL помечать невыбранные варианты как отклонённые

## Решение

Контракт-заглушка находится в `wp/themes/velotok/inc/theme-design-variants.php`
и помечен `mocked => true`. В контракт добавлены явные маркеры
`reject_unselected => true` и `unselected_status => 'rejected'`.

Операция `velotok_theme_select_design_variant()` выбирает ровно один вариант,
помечает его `selected`, а все остальные варианты — `rejected`. Для этого
добавлены публичные помощники:

* `velotok_theme_design_variant_status()` — статус варианта при выборе;
* `velotok_theme_design_rejected_variants()` — список отклонённых вариантов.

Ошибочный запрос без выбора оставляет варианты в статусе `pending`, поэтому
`rejected` всегда означает именно невыбранный вариант, а не ошибку ввода.
Реальная интеграция, сеть и сохранение состояния не используются.

## Контрактный тест

`wp/themes/velotok/tests/theme-design-variants-rejected-contract-test.php`
проверяет метку `mocked`, маркеры отклонения, статус каждого варианта, список
`rejected`/`rejected_variants` и помощники статуса. Тест завершается с ошибкой,
если файл заглушки отсутствует.

`wp/themes/velotok/tests/theme-design-variants-rejected-redgreen.test.js`
даёт red-green доказательство:

* с настоящей заглушкой тест зелёный;
* без файла заглушки тест падает;
* с мутированной копией заглушки, где невыбранные варианты больше не получают
  `rejected`, тест падает.

## Проверено

```bash
php wp/themes/velotok/tests/theme-design-variants-contract-test.php
php wp/themes/velotok/tests/theme-design-variants-rejected-contract-test.php
node wp/themes/velotok/tests/theme-design-variants-contract.test.js
node wp/themes/velotok/tests/theme-design-variants-rejected-redgreen.test.js
php -l wp/themes/velotok/inc/theme-design-variants.php
php -l wp/themes/velotok/tests/theme-design-variants-rejected-contract-test.php
```

`wp theme list` требует запущенного WordPress/Docker-окружения; в текущем
окружении проверка выполнена автономными контрактными тестами.
