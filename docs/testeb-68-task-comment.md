# Запись комментария к задаче TESTEB-68

- Задача: TESTEB-68 — CI не дал результат (`unknown`) по PR #78 (TESTEB-31)
- Записано: 2026-11-08
- PR #78 на момент постановки: коммит `841470c19d9e62c1a6c4e4c402dff322c81fd747`,
  прогон `ci` #42500219010 на мерж-коммите
  `b846d37d24964e61485f0c321d48db64c1ba6530`, последнее состояние check
  `php-tests` — `in_progress`.

## Причина

Джоба `php-tests` в прогоне #42500219010 фактически завершилась успешно: все
theme-тесты прошли, после них выполнился `Post job cleanup` и очистка
orphan-процессов. Несмотря на это, у коммита остался `in_progress`-дубликат
check run с тем же именем `php-tests` (перезапуск/отменённый предыдущий прогон
из-за `concurrency`). Проверка статусов принимала любой pending-дубликат за
блокирующий, поэтому отложенный мерж не дождался результата и через 30 минут
получил класс `unknown`.

Recovery-механизм из TESTEB-67 был зашит на PR #73, поэтому для PR #78 не
существовало автоматического пути проверки и завершения мержа.

## Что исправлено

- `scripts/ci/lib.mjs`: для check runs и commit statuses выбирается самый
  свежий результат по имени/контексту; старый pending-дубликат больше не
  перекрывает успешно завершённую проверку. При публикации recovery завершает
  все оставшиеся pending-дубликаты.
- `scripts/ci/lib.mjs` (вторая причина, найденная на реальном прогоне): если
  чужой check run нельзя обновить через PATCH (`403 Resource not accessible by
  integration`), recovery больше не падает, а публикует новый завершённый check
  run. Без этого первый recovery-прогон #42504665607 обрывался на шаге
  публикации и не доходил ни до `ci/recover`, ни до мержа.
- `.github/workflows/ci.yml`: `cancel-in-progress: false`, чтобы отменённые
  прогоны не оставляли зависшие `in_progress` check runs.
- `.github/workflows/recover-pr.yml`: общий recovery для будущих PR — запуск
  после завершения `ci`, проверяет только реально зависшие/отсутствующие
  результаты, не маскирует настоящие падения.
- `.github/workflows/recover-pr78.yml` и
  `.github/workflows/finalize-pr78-evidence.yml`: recovery и финализатор для
  текущего PR #78; recovery берёт актуальный head, прогоняет полный набор
  проверок `scripts/ci/run-checks.sh` и завершает отложенный мерж.
  Финализатор находит recovery-прогон по SHA триггера и не берёт run id из
  собственного `GITHUB_RUN_ID`. Группа
  `php-plugin-tests` исполняет обязательную команду
  `php wp/plugins/velotok-core/tests/accounts-contract-test.php`.
- `scripts/ci/resolve-pr-target.mjs` и `scripts/ci/should-recover.mjs` умеют
  разрешать PR по коммиту, поэтому recovery не привязан к номеру задачи.
- `scripts/ci/evidence.mjs`: в блок доказательств попадает только самый свежий
  check run для каждого имени, поэтому зависший `in_progress`-дубликат больше
  не выглядит как незавершённая проверка рядом с зелёным результатом.
- `scripts/ci/tests/pr78-recovery-evidence.test.mjs`: red-green тест на реальном
  снимке PR #78 — инцидентное состояние (упавший run #42504665607, PR не
  смержен) обязано быть red, а фактическое post-fix состояние (run #42505520113,
  merge `330a631...`) — green.

## Доказательства

- Проверяемый коммит (актуальный head PR #78):
  `841470c19d9e62c1a6c4e4c402dff322c81fd747`.
- Исходный CI-прогон:
  [#42500219010](https://github.com/sarnas-it/ebike-site/actions/runs/42500219010)
  — джоба `php-tests` успешна по журналу, но check run `php-tests` id
  `128791152777` остался `status=in_progress` при `conclusion=success` и
  `completed_at=2026-11-08T16:30:32Z`; именно он блокировал обязательную
  проверку.
- Первый recovery-прогон
  [#42504665607](https://github.com/sarnas-it/ebike-site/actions/runs/42504665607)
  упал на шаге `Publish checks and complete deferred merge`: на коммите не
  появились ни новые check runs, ни `ci/recover`, потому что PATCH чужого
  check run вернул `403 Resource not accessible by integration`. Это и есть
  причина, по которой предыдущая попытка починки не довела PR #78 до мержа.
- Red-green тесты на исправление:
  `node scripts/ci/tests/stale-checks.test.mjs` и
  `node scripts/ci/tests/recover-pr.test.mjs` воспроизводят `403` на PATCH
  чужого check run и проверяют, что recovery публикует новые завершённые
  проверки на нужном SHA и всё равно завершает отложенный мерж. Без ветки
  fallback в `upsertCheckRun` эти тесты падают.
- Фактический post-fix recovery-прогон:
  [#42505520113](https://github.com/sarnas-it/ebike-site/actions/runs/42505520113)
  (`.github/workflows/recover-pr78.yml`, событие `push`) —
  `status=completed`, `conclusion=success`, runner head
  `65899b710cb71e2dea0c5b3ec46dc3171fac5a4f`, ветка
  `feat/ebike-site-testeb-68`.
- На проверяемом head `841470c...` опубликованы завершённые зелёные check runs:
  `php-lint` (128807297687), `php-tests` (128807302983), `plugin-tests`
  (128807306499), `php-plugin-tests` (128807310353), `ci-repair-tests`
  (128807314799). Старый `in_progress`-дубликат `128791152777` остаётся в
  истории, но для `php-tests` authoritative является более свежий завершённый
  run.
- Commit status `ci/recover=success` на `841470c...` ссылается на
  [#42505520113](https://github.com/sarnas-it/ebike-site/actions/runs/42505520113),
  поэтому зелёные проверки привязаны к реальному run.
- Отложенный мерж PR #78 выполнен без ручного вмешательства:
  `merged=true`, merge commit
  `330a631e8655c0ae1f2c8d76a011ae319e90a0ac`, merged at
  `2026-11-08T17:33:38Z`; финализатор
  [Finalize PR #78 evidence #42505520131](https://github.com/sarnas-it/ebike-site/actions/runs/42505520131)
  завершился `completed/success`.
- Red-green тест на критерии приёмки:
  `node scripts/ci/tests/pr78-recovery-evidence.test.mjs` — инцидентное
  состояние red, фактическое post-fix состояние green; evidence-блок содержит
  реальный run, merge commit и итог `доказательства полные (green)`.
- Локальная проверка полного набора:
  `for f in wp/plugins/velotok-core/tests/*-test.php wp/themes/velotok/tests/*-test.php; do php "$f" || exit 1; done`
  — все тесты проходят.
