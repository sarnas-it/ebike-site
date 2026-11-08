# Комментарий к задаче TESTEB-67

<!-- testeb-67-recovery -->

Причина `no_checks` по PR [#73](https://github.com/sarnas-it/ebike-site/pull/73):
застрявший коммит `525b0d4803e03858cdb0bfce9cb90837bdf0473e` не содержит
`.github/workflows` в своём дереве, а в базовой ветке `main` на момент открытия
и последнего push PR workflow тоже не было. На момент постановки текущий `main`
(`99f296a30502af717ac31af5794928e5e540b136`, мерж PR #71) состоял только из
`wp/**` и корневого `verify.log`, каталога `.github/` в нём не было. Для события `pull_request` GitHub ищет workflow в дереве PR (head +
base); ни в head, ни в base файла не было, поэтому запускать было нечего —
отсюда `no checks reported for the pull request`. Patch PR #73 меняет `wp/**`
и добавляет корневой `verify.log`, но workflow не добавляет. GitHub не
запускает workflow ретроспективно,
поэтому появление файла в другой ветке или позже в `main` само по себе не
создаёт прогон для неизменённого старого head.

Это не противоречит тому, что Actions включены, а соседние PR зелёные: в
ветках соседних задач есть собственный `.github/workflows/ci.yml`, поэтому в их
PR-деревьях workflow присутствует. В ветке PR #73 такого файла нет, а `main`
тоже без него. Фильтров по путям в CI нет, очередь раннеров и лимиты минут не
при чём (прогон для этого SHA вообще не создавался), allow-list интеграции
содержит `sarnas-it/ebike-site`.

Исправление:

* `.github/workflows/ci.yml` запускается на каждом `pull_request` (без
  `paths`-фильтра), на `push` в `main` и по `workflow_dispatch`, поэтому после
  мержа починки новые PR получают реальные прогоны;
* recovery-workflow `Recover PR #73 checks`
  (`.github/workflows/recover-pr73.yml`) на каждом запуске берёт **актуальный
  head PR #73** через GitHub API (`scripts/ci/resolve-pr-target.mjs`), а не
  зашитый SHA из инцидента. Коммит `525b0d48` сохранён только как
  `INCIDENT_SHA` для аудита: если head уедет, recovery проверит и смержит
  именно новый head, а не будет пропускать мерж. Workflow выкачивает
  разрешённый коммит, сверяет `git rev-parse HEAD` с ним, прогоняет те же
  проверки, что и обычный CI (`php-lint`, `php-tests`, `plugin-tests`,
  `php-plugin-tests`, включая обязательную команду
  `php wp/plugins/velotok-core/tests/accounts-contract-test.php`),
  публикует результаты на этот SHA и завершает отложенный мерж PR #73. Мерж
  выполняется только если все проверки зелёные и head PR всё ещё равен
  проверенному коммиту; если head уехал во время прогона, следующий push в PR
  автоматически запускает recovery по новому head;
* финальное подтверждение публикует отдельный workflow
  `Finalize PR #73 evidence`, запускаемый тем же `push`, а не через
  `workflow_dispatch`. Он находит recovery-прогон по точному SHA триггера,
  дожидается его завершения и только затем обновляет этот комментарий,
  поэтому в доказательствах фиксируется `completed/success`, а не
  `in_progress`. Финализатор больше не берёт `runId` из своего
  `GITHUB_RUN_ID`: при наличии `--trigger-sha` он обязан найти именно
  recovery-прогон, иначе он ждал бы сам себя и не опубликовал бы этот
  комментарий. Первый recovery-прогон не смог задиспатчить финализатор,
  потому что workflow ещё не было в базовой ветке; проверки и мерж при этом
  уже прошли.

<!-- testeb-67-evidence:start -->
## Подтверждение из реального recovery-прогона

- Проверяемый коммит (актуальный head PR #73): `525b0d4803e03858cdb0bfce9cb90837bdf0473e`
- Post-fix recovery workflow run: будет опубликован автоматически после push; финализатор заменит этот блок реальными данными из GitHub API.
- Ожидаемые проверки на коммите: `php-lint`, `php-tests`, `plugin-tests`, `php-plugin-tests`.
- Обязательная команда `php wp/plugins/velotok-core/tests/accounts-contract-test.php` входит в `php-plugin-tests`.
- PR #73 мержится recovery-прогоном только после зелёных проверок; итоговый merge commit берётся из API.
- Итог: это черновик; после recovery-прогона здесь будут фактические `completed/success` доказательства со ссылками на run и merge commit.
<!-- testeb-67-evidence:end -->
