# Комментарий к задаче TESTEB-64

<!-- testeb-64-recovery -->

Причина `no_checks` по PR [#60](https://github.com/sarnas-it/ebike-site/pull/60):
застрявший коммит `b1b0871d6ebb4f7ce25a987c956c7b1431eef379` не содержит
`.github/workflows` в своём дереве, а в базовой ветке `main` на момент открытия
и последнего push PR workflow тоже не было. На момент постановки текущий `main`
(`efe1bc4`, мерж PR #62) состоял только из `wp/**`, корневого `.github/` в нём
не было. Для события `pull_request` GitHub ищет workflow в дереве PR (head +
base); ни в head, ни в base файла не было, поэтому запускать было нечего —
отсюда `no checks reported for the pull request`. Patch PR #60 меняет только
`wp/**` и workflow не добавляет. GitHub не запускает workflow ретроспективно,
поэтому появление файла в другой ветке или позже в `main` само по себе не
создаёт прогон для неизменённого старого head.

Это не противоречит тому, что Actions включены, а соседние PR зелёные: в
ветках соседних задач есть собственный `.github/workflows/ci.yml`, поэтому в их
PR-деревьях workflow присутствует. В ветке PR #60 такого файла нет, а `main`
тоже без него. Фильтров по путям в CI нет, очередь раннеров и лимиты минут не
при чём (прогон для этого SHA вообще не создавался), allow-list интеграции
содержит `sarnas-it/ebike-site`.

Исправление:

* `.github/workflows/ci.yml` запускается на каждом `pull_request` (без
  `paths`-фильтра), на `push` в `main` и по `workflow_dispatch`, поэтому после
  мержа починки новые PR получают реальные прогоны;
* recovery-workflow `Recover PR #60 checks`
  (`.github/workflows/recover-pr60.yml`) на каждом запуске берёт **актуальный
  head PR #60** через GitHub API (`scripts/ci/resolve-pr-target.mjs`), а не
  зашитый SHA из инцидента. Коммит `b1b0871` сохранён только как
  `INCIDENT_SHA` для аудита: если head уедет, recovery проверит и смержит
  именно новый head, а не будет пропускать мерж. Workflow выкачивает
  разрешённый коммит, сверяет `git rev-parse HEAD` с ним, прогоняет те же
  проверки, что и обычный CI (`php-lint`, `php-tests`, `php-plugin-tests`,
  `plugin-tests`, `ci-repair-tests`), публикует результаты
  на этот SHA и завершает отложенный мерж PR #60. Мерж выполняется только если
  все проверки зелёные и head PR всё ещё равен проверенному коммиту; если head
  уехал во время прогона, следующий push в PR автоматически запускает recovery
  по новому head. Приёмочная PHP-команда
  `php wp/plugins/velotok-core/tests/accounts-contract-test.php` входит в
  общий набор проверок;
* финальное подтверждение публикует отдельный workflow
  `Finalize PR #60 evidence`: он запускается тем же push, находит
  recovery-прогон по точному SHA триггера, дожидается его завершения и только
  затем обновляет этот комментарий, поэтому в доказательствах фиксируется
  `completed/success`, а не `in_progress`.

<!-- testeb-64-evidence:start -->
## Подтверждение из реального recovery-прогона

- Проверяемый коммит (актуальный head PR): `b1b0871d6ebb4f7ce25a987c956c7b1431eef379`
- Recovery workflow run: [#42469606322](https://github.com/sarnas-it/ebike-site/actions/runs/42469606322) — status=completed, conclusion=success, event=push, runner head=`1c5d72de7533e42e88a70a0c637376966f3b7d8d`
- Ветка прогона: `feat/ebike-site-testeb-64`
- Проверки на проверяемом коммите: `php-lint=success`, `php-tests=success`, `plugin-tests=success`, commit status `ci/recover=success`
- PR merged=true, merge commit=`4f8dce6768556a015b4f284c09d1c336c2cba292`, merged at=`2026-11-08T10:01:27Z`
- Head PR совпадает с проверенным коммитом: true
- Итог: доказательства полные (green)
<!-- testeb-64-evidence:end -->
