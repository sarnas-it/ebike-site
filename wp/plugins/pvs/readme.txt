=== Велоток: проверка плагинов на уязвимости ===
Contributors: velotok
Tags: security, plugins, vulnerabilities
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Проверка всех установленных плагинов на уязвимости в mocked-режиме.

== Description ==

Единая точка доступа — `pvs_contract()` в `includes/class-pvs-contract.php`.
Контракт помечен `mocked => true`, `network => false`, `source => stub` и
`all_plugins => true`.

Проверяются все плагины, включая неактивные, mu-плагины и drop-ins.
Поддерживаемые источники проверки описаны в `pvs_contract()['providers']`:
WPScan (`wpscan`), Patchstack (`patchstack`) и ручной аудит (`manual`).
Провайдер выбирается опцией `provider`; находки ручного аудита передаются в
`manual_findings` (алиасы: `manual_audit`, `manual_vulnerabilities`, `findings`)
и попадают в отчёт с той же подсветкой рисков.

Результат возвращает `pvs_scan_all_plugins()` / `PVS_Scanner::scan()`:

* список проверенных плагинов с версиями и статусом;
* список найденных уязвимостей с идентификатором, важностью и исправленной версией;
* маркеры `mocked`, `source`, `network` на уровне отчёта, плагина и уязвимости;
* подсветку рисков по важности (`pvs-risk-critical/high/medium/low`) в отчёте
  и таблице плагинов, с CSS в админке. Формат подсветки зафиксирован в
  `pvs_contract()['highlight_format']`: классы `pvs-risk-*`, `pvs-severity-*`,
  `pvs-plugin-vulnerable`, `pvs-risk-highlight` и data-атрибуты
  `data-highlight`, `data-severity`, `data-risk`.

Страница проверки находится в админке: «Инструменты → Проверка плагинов».
Форма защищена nonce и проверкой права `manage_options`.

Заглушка не читает env, файлы, БД и не обращается к внешним сервисам. Имена
будущих env-переменных (`VELOTOK_PVS_API_URL`, `VELOTOK_PVS_API_TOKEN`,
`VELOTOK_PVS_TIMEOUT`) только вычисляются.

Запуск контрактного теста:

```bash
php wp/plugins/pvs/tests/pvs-contract-test.php
php wp/plugins/pvs/tests/pvs-all-plugins-contract-test.php
php wp/plugins/pvs/tests/pvs-risk-highlighting-contract-test.php
php wp/plugins/pvs/tests/pvs-providers-manual-contract-test.php
node wp/plugins/pvs/tests/pvs-contract.test.js
node wp/plugins/pvs/tests/pvs-risk-highlighting-redgreen.test.js
```
