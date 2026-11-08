# CI

## Причина `no_checks` по PR #62 (TESTEB-63)

Застрявший коммит `578e87b5a8587d6078aade92d26bd449448dd1d3` (head ветки
`feat/ebike-site-testeb-1`) не содержит `.github/workflows` в своём дереве. В
момент открытия и последнего push этого PR базовая ветка `main` тоже была
пустой (`40134b5`, `chore: empty repository`) и workflow в ней не было. Для
события `pull_request` GitHub ищет workflow-файл в дереве PR (merge-дерево:
head + base). Ни в head, ни в base файла не было, поэтому запускать было
нечего — отсюда `no checks reported for the pull request`.

## Причина `no_checks` по PR #73 (TESTEB-67)

Застрявший коммит `525b0d4803e03858cdb0bfce9cb90837bdf0473e` (head ветки
`feat/ebike-site-testeb-3`) не содержит `.github/workflows` в своём дереве.
Это проверено по содержимому самого коммита: `git ls-tree` и
`GET /contents/.github/workflows` для этого SHA не находят каталог workflow, а
patch PR #73 меняет файлы внутри `wp/**` и добавляет корневой `verify.log`, но workflow не добавляет.

Проблема не только в head: в базовой ветке `main` на момент постановки задачи
тоже не было `.github/workflows`. `main` тогда указывал на
`99f296a30502af717ac31af5794928e5e540b136` (мерж PR #71), и в его дереве были
только `wp/**` и корневой `verify.log`; каталога `.github/` не было. Для события `pull_request` GitHub ищет workflow-файл в дереве PR
(merge-дерево head + base). Ни в head, ни в base файла не было, поэтому
запускать было нечего — отсюда `no checks reported for the pull request`.

GitHub не запускает workflow задним числом: появление файла в другой ветке или
позже в `main` не создаёт прогон для уже существующего неизменённого head PR.
Нужен либо новый коммит в head (новое событие `pull_request`/`synchronize`),
либо отдельный прогон, который явно проверит застрявший или актуальный коммит.

### PR #62 (TESTEB-63)

Это подтверждается данными API на момент разбора:

* `GET /repos/sarnas-it/ebike-site/commits/578e87b.../actions/runs` —
  `total_count: 0` (нативного прогона у старого SHA нет и не будет);
* в дереве `578e87b` нет `.github/workflows`;
* в `main` на тот момент workflow тоже не было.

### Почему это не противоречит «ci active» и зелёным соседним PR

Workflow `ci` действительно активен, а соседние PR получают зелёные прогоны,
но не потому, что workflow уже был в `main`. Ветки соседних задач
(`feat/ebike-site-testeb-15`, `...-44`, `...-50`) сами добавляют
`.github/workflows/ci.yml` в своё дерево, поэтому в их PR-дереве workflow есть.
Ветка PR #62 такого файла не добавляла, а `main` в тот момент был пустым. То
есть «ci active» относится к версиям файла в ветках соседних PR, а не к дереву
застрявшего коммита. Противоречия нет: отсутствие workflow именно в дереве
PR #62 — и есть причина.

Остальные возможные причины исключены:

* GitHub Actions в репозитории включены (workflow в соседних ветках
  запускаются);
* фильтров по путям и веткам в CI нет: триггер `pull_request` широкий;
* очередь раннеров и лимит минут не при чём: job на `ubuntu-latest`
  завершается за секунды, как видно по успешным прогонам;
* allow-list интеграции содержит `sarnas-it/ebike-site`: PR создавался через
  интеграцию, и её токен открывает PR в этом репозитории.

### PR #73 (TESTEB-67)

Проверенные данные:

* PR #73 — head `525b0d4803e03858cdb0bfce9cb90837bdf0473e`, base `main`;
* в дереве `525b0d48` нет `.github/workflows` (raw/contents возвращает `404`);
* в `main` на момент постановки (`99f296a30502af717ac31af5794928e5e540b136`, мерж
  PR #71) не было `.github/workflows`; в корне были только `wp/**` и
  `verify.log`;
* patch PR #73 добавляет и меняет `wp/**` и `verify.log`, workflow не добавляет.

Предварительная проверка целевого коммита: клон ветки
`feat/ebike-site-testeb-3` на `525b0d48` прогнан через
`bash scripts/ci/run-checks.sh <checkout>`; `php-lint`, `php-tests`,
`plugin-tests` и `php-plugin-tests` завершились успешно. Значит, после
публикации этих же проверок recovery-workflow сможет завершить отложенный мерж
без ручного вмешательства.

Остальные возможные причины исключены:

* GitHub Actions в репозитории включены: PR из соседних задач, в чьих ветках
  есть `.github/workflows/ci.yml`, получают прогоны;
* фильтров по путям и веткам в CI нет: триггер `pull_request` широкий, без
  `paths`;
* очередь раннеров и лимит минут не при чём: прогон для этого SHA вообще не
  создавался;
* allow-list интеграции содержит `sarnas-it/ebike-site`, иначе PR #73 не был
  бы создан.

## Фактический результат восстановления PR #62

Постоянные доказательства, снятые с GitHub после recovery-прогона:

| Что | Значение |
| --- | --- |
| PR #62 | `closed`, `merged: true`, `merged_at: 2026-11-08T04:03:25Z` |
| Merge commit | `efe1bc4dac551dc870cec68aff16a973e095dd4f` |
| Recovery workflow run | [#42445204104](https://github.com/sarnas-it/ebike-site/actions/runs/42445204104) — job `recover` завершился успешно |
| Проверки на `578e87b` | `php-lint=success`, `php-tests=success`, `plugin-tests=success` (3 check runs + commit status `ci/recover=success`) |

### Как подтверждаются критерии 2 и 3 (PR #65)

Трекер мержит PR #65 только после ACCEPT, поэтому требовать состояние
`merged: true` до приёмки нельзя: такой гейт был бы красным по определению и
ничего не доказывал. Доказательством служит не «будущий» мерж, а штатный
прогон `ci` на **коммите, который сдаётся** — актуальном head PR #65 до мержа
(обычный workflow run и его check runs, а не синтетические Check Runs API).
Поэтому:

* `verify-ci-rollout` запускается на самом PR #65 (`pull_request`), а не только
  на `push` в `main`;
* workflow фиксирует проверяемый коммит в `TARGET_SHA` из события:
  `github.event.pull_request.head.sha` для `pull_request` (ровно тот head,
  который сдаётся). Оба шага — `verify-rollout.mjs` и
  `publish-rollout-evidence.mjs` — получают этот SHA через `--sha`, а не берут
  «какой-нибудь текущий head». Для `push` в `main` `TARGET_SHA` пустой, и гейт
  сам выбирает коммит, который реально лёг в базовую ветку: merge commit PR #65
  после мержа (до мержа — head PR #65), поэтому более поздний push в `main` не
  ломает уже зафиксированное доказательство;
* гейт `scripts/ci/verify-rollout.mjs` зелёный, когда PR #65 открыт,
  `mergeable=true` и нет конфликтов с `main`, а прогон `ci` на этом
  зафиксированном коммите завершился успешно со всеми job; после мержа тот же
  гейт проверяет **merge commit** и требует успешный прогон `ci` уже на нём;
* успешный прогон на другом (например, более старом) коммите не принимается:
  `head_sha` прогона, `head_sha` каждого check run и `TARGET_SHA` должны
  совпадать, поэтому зелёный прогон прошлой сдачи (например, `7052bf2`) не
  может подтвердить новый сдаваемый коммит (`b774c408` или его преемник);
* штатный check run должен принадлежать именно check suite проверяемого
  прогона `ci` (сравниваются `check_suite_id` прогона и check run). Совпадение
  ссылки `details_url` на run без совпадения suite доказательством не является;
* workflow `.github/workflows/ci.yml` читается из дерева head PR #65 до мержа и
  из `main` после него — именно оттуда GitHub берёт его для прогона.

Единственный надёжный момент, когда прогон `ci` на сдаваемом коммите уже
существует, — это сам PR #65: любой новый push создаёт событие
`pull_request`/`synchronize`, а значит и новый прогон на новом head. После
мержа очередь трекера создаёт `push` в `main`, поэтому появляется прогон уже на
merge commit. Ссылка на run, его `head_sha`, список job, штатные check runs и
состояние PR (`mergeable`, `mergeable_state`, `merged`, merge commit)
фиксируются в выводе гейта, в `$GITHUB_STEP_SUMMARY`, в обычном check run
`verify-ci-rollout` и в комментарии `<!-- testeb-63-rollout -->`. Поэтому
приёмка всегда сверяется с фактическим проверяемым коммитом (head до мержа,
merge commit после), а не с захардкоженным SHA из документации. До ACCEPT
состояние `merged` в комментарии — `false`, а `Merge commit` — `none`; это
ожидаемо, потому что мерж выполняет очередь трекера. После мержа workflow на
`push` в `main` обновляет тот же комментарий, и в нём появляются
`merged=true` и фактический merge commit.

Нативного `actions/runs` у старого SHA по-прежнему `0`: GitHub не создаёт
прогоны ретроспективно. Поэтому проверки на `578e87b` опубликованы
recovery-workflow, который реально выкачал этот SHA и прогнал на нём
`scripts/ci/run-checks.sh`. Чтобы такие check runs не принимались «на веру»,
добавлен строгий evidence-гейт: зелёные check runs считаются доказательством
только вместе с завершившимся успехом recovery-workflow run, который их
породил.

## Что исправлено

### 1. Обычный CI для будущих PR

`.github/workflows/ci.yml` запускается:

* на каждом `pull_request` без фильтров по путям и веткам;
* на `push` в `main`;
* вручную через `workflow_dispatch`.

Проверки: `php-lint`, `php-tests`, `php-plugin-tests`, `plugin-tests`,
`ci-repair-tests`. Набор проверок только расширен, ни одна проверка не
отключена. `php-plugin-tests` действительно исполняет все
`wp/plugins/velotok-core/tests/*-test.php` через `php`, включая приёмочную
команду `php wp/plugins/velotok-core/tests/accounts-contract-test.php`, а не
только проверяет синтаксис через `php -l`. `ci-repair-tests` запускает все
`scripts/ci/tests/*.test.mjs`, поэтому новые red-green тесты не могут быть
пропущены.

После мержа этой починки в `main` файл `.github/workflows/ci.yml` окажется в
базовой ветке, и новые PR будут получать реальные прогоны.

Готовность починки (критерии 2 и 3) и успешный прогон CI именно на реальном
head-коммите PR #65 проверяются red-green гейтом
`scripts/ci/verify-rollout.mjs`. Гейт возвращает код 1, пока не выполнено хотя
бы одно условие:

* PR [#65](https://github.com/sarnas-it/ebike-site/pull/65) нацелен в `main` и
  либо открыт с `mergeable=true` без конфликтов, либо уже смержен (тогда есть
  merge commit);
* `.github/workflows/ci.yml` присутствует в дереве head PR #65 (до мержа) или в
  `main` (после мержа);
* прогон `ci` на **сдаваемом коммите** (`TARGET_SHA`: head PR #65 до мержа,
  merge commit после) завершился успешно, а не прогон на каком-то другом,
  более старом SHA;
* в прогоне присутствуют все job: `php-lint`, `php-tests`, `plugin-tests`,
  `ci-repair-tests`, и каждая завершилась успешно;
* на этом же проверяемом коммите есть **штатные check runs** этих job,
  привязанные к check suite проверяемого прогона `ci`, и их `head_sha` равен
  `TARGET_SHA`. Зелёные check runs, созданные через Checks API в обход
  workflow (например, recovery-скриптом), или принадлежащие другому check
  suite, гейт не принимает: они не доказывают, что workflow отработал на этом
  коммите.

`.github/workflows/verify-ci-rollout.yml` запускает этот гейт на самом PR #65
(`pull_request`), на каждый `push` в `main` и вручную. Прогон на `pull_request`
и есть штатное доказательство критерия 2: он прикрепляет обычный check run
`verify-ci-rollout` к сдаваемому head PR #65 и проверяет прогон `ci` на этом
же SHA. Мерж до ACCEPT не требуется, потому что его выполняет очередь трекера;
после мержа гейт остаётся зелёным и дополнительно подтверждает прогон `ci` и
штатные check runs уже на merge commit.
Успешный прогон на другом (старом) SHA гейт не принимает, поэтому, например,
зелёный прогон на `7052bf2` не может подтвердить сдаваемый `b774c408` (или его
преемника). SHA берётся из события запуска (`TARGET_SHA`) и передаётся
`--sha`, поэтому доказательство всегда относится именно к тому коммиту,
который сдаётся, а не к предыдущей сдаче.

После зелёного гейта шаг `publish-rollout-evidence.mjs` создаёт или обновляет
один идемпотентный комментарий на PR #65 (`<!-- testeb-63-rollout -->`) со
ссылкой на штатный run, его `head_sha`, списком job, штатными check runs,
состоянием `mergeable`/конфликтов и merge commit (если PR уже смержен).
Комментарий привязан к фактическому head на момент прогона и не публикуется,
пока доказательства красные. Тот же текст сохраняется как артефакт workflow
`rollout-evidence`, поэтому состояние `merged`/merge commit и ссылка на run
зафиксированы не только в редактируемом комментарии. До ACCEPT PR #65 ещё не
смержен, поэтому `merge commit` в нём `none`; после мержа тот же workflow
перезапускается на `main`, гейт читает workflow из базовой ветки и записывает
фактический merge commit.

### 2. Recovery для уже застрявшего PR #62

`.github/workflows/recover-pr62.yml` запускается на `push` и вручную и:

1. выкачивает **ровно коммит**
   `578e87b5a8587d6078aade92d26bd449448dd1d3` (`git rev-parse HEAD`
   сверяется с `TARGET_SHA`);
2. прогоняет на нём те же проверки через общий
   `scripts/ci/run-checks.sh` (`php-lint`, `php-tests`, `plugin-tests`);
3. публикует результаты как check runs с именами `php-lint`, `php-tests`,
   `plugin-tests` и commit status `ci/recover` **на этот же SHA**;
4. завершает отложенный мерж PR #62 (если он ещё открыт и head всё ещё равен
   проверенному коммиту);
5. запускает `scripts/ci/publish-evidence.mjs`, который проверяет реальный
   успешный workflow run, зелёные проверки на целевом SHA и наличие merge
   commit, после чего создаёт **или обновляет** один идемпотентный комментарий
   к PR #62 с фактическими ссылками на run, проверки и merge commit. Черновик
   больше не публикуется «как есть»: текст дополняется данными из API.

`run-name` recovery-прогона содержит целевой SHA, поэтому прогон можно
однозначно сопоставить с проверяемым коммитом.

### 3. Recovery для уже застрявшего PR #73

`.github/workflows/recover-pr73.yml` запускается на `push` и вручную и:

1. разрешает **актуальный head PR #73** через GitHub API
   (`scripts/ci/resolve-pr-target.mjs`) и выкачивает именно его
   (`git rev-parse HEAD` сверяется с разрешённым SHA). SHA из инцидента
   `525b0d4803e03858cdb0bfce9cb90837bdf0473e` остаётся только в
   `INCIDENT_SHA` для аудита: recovery не привязан к нему и не пропускает
   мерж, если head уехал;
2. прогоняет на разрешённом коммите те же проверки через общий
   `scripts/ci/run-checks.sh` (`php-lint`, `php-tests`, `php-plugin-tests`,
   `plugin-tests`);
3. публикует результаты как check runs с именами `php-lint`, `php-tests`,
   `php-plugin-tests`, `plugin-tests` и commit status `ci/recover` **на этот
   же SHA**;
4. завершает отложенный мерж PR #73 (если он ещё открыт и head всё ещё равен
   проверенному коммиту). Если head уехал между проверкой и мержем, мерж
   безопасно пропускается; следующий push в PR-ветку запускает recovery уже
   по новому head;
5. публикует доказательства через отдельный workflow `Finalize PR #73
   evidence` (`.github/workflows/finalize-pr73-evidence.yml`), который
   запускается тем же `push`, а не диспатчится из recovery. GitHub разрешает
   `workflow_dispatch` только для workflow, уже существующего в базовой ветке;
   именно на этом падал первый recovery-прогон после того, как проверки уже
   были зелёными и PR уже был смержен. Теперь финализатор сам находит
   recovery-прогон по точному SHA триггера (`--trigger-sha`), ждёт его
   завершения и только потом публикует доказательства через
   `scripts/ci/evidence.mjs`.
   Если передан `--trigger-sha`, `wait-and-publish-evidence.mjs` **не**
   подставляет собственный `GITHUB_RUN_ID`: иначе финализатор ждал бы сам
   себя, никогда не увидел бы recovery-прогон и не опубликовал комментарий.
   Публикация проверяет реальный успешный workflow run, зелёные проверки на
   проверенном SHA и наличие merge commit, после чего создаёт **или обновляет** один
   идемпотентный комментарий к PR #73 с фактическими ссылками на run, проверки
   и merge commit. Поэтому в комментарий никогда не попадает `in_progress` как
   итоговый результат. Черновик не публикуется «как есть»: блок доказательств
   между маркерами `<!-- testeb-67-evidence:start/end -->` заменяется данными
   из API, поэтому старые in-progress доказательства не остаются и не
   дублируются.

`name` и `run-name` workflow взяты в кавычки: без них `#73` в YAML считается
началом комментария, и GitHub показывал бы прогон как «Recover PR». Теперь имя
и run-name однозначно указывают на PR #73, а проверяемый SHA, ветка и факт
дрейфа пишутся в summary прогона.

Повторные запуски идемпотентны: существующие check runs обновляются,
комментарий обновляется, а не дублируется, уже смерженный PR повторно не
мержится, а уехавший head не мержится вслепую.

### Результат после починки

Починка уже отработала на реальном PR #73, поэтому ниже зафиксированы
фактические идентификаторы из GitHub API, а не черновик:

* recovery-прогон
  [#42492889252](https://github.com/sarnas-it/ebike-site/actions/runs/42492889252)
  — `status=completed`, `conclusion=success`, `event=push`, workflow
  `.github/workflows/recover-pr73.yml`;
* проверяемый head PR #73 — `525b0d4803e03858cdb0bfce9cb90837bdf0473e`;
  recovery исполнялся на коммите-триггере
  `2ef3bdca990ab39a81133a06751ceab8fed20aaf`, а на проверяемый head
  опубликован commit status `ci/recover=success` со ссылкой на этот run;
* зелёные check runs на проверяемом head: `php-lint` (128768985954),
  `php-tests` (128768989481), `plugin-tests` (128768992188),
  `php-plugin-tests` (128768994590) — все `completed/success`;
* PR #73 смержен без ручного вмешательства
  `2026-11-08T15:04:35Z`, merge commit
  `6a14f53349e6f7da967e1bd1aded48c12deb82be`.

После применения починки:

1. recovery-workflow `Recover PR #73 checks` находит актуальный head PR #73,
   прогоняет на нём общий набор проверок и публикует check runs на этот SHA;
2. при зелёных проверках recovery завершает отложенный мерж PR #73;
3. push-триггер `Finalize PR #73 evidence` находит recovery-прогон по SHA
   триггера, дожидается его `completed/success` и заменяет блок доказательств
   в `docs/testeb-67-comment.md` реальными ссылками на run, проверки и merge
   commit, которые получены из GitHub API.

`verify-recovery.mjs` без `--run-id` больше не берёт произвольный «последний
успешный» прогон: он читает commit status `ci/recover` на проверяемом коммите
и достаёт из его `target_url` точный recovery-run. Это связывает проверки с
конкретным прогоном, который их опубликовал, и делает доказательства
неподдельными. Тест `scripts/ci/tests/evidence.test.mjs` краснеет, если
зелёные check runs не привязаны к `ci/recover`, и проходит на реальной связке.

Первый push-триггерный finalizer-прогон завершился `failure` на шаге
публикации комментария. Чтобы публикация комментария к pull request была
разрешена, workflow теперь запрашивает `pull-requests: write` (раньше был
только `issues: write`); `scripts/ci/tests/workflow-triggers.test.mjs`
проверяет наличие этого права. Сами проверки и мерж к этому моменту уже
прошли.

`wait-and-publish-evidence.mjs` при `--trigger-sha` намеренно игнорирует
собственный `GITHUB_RUN_ID`: иначе финализатор ждал бы сам себя, никогда не
увидел бы recovery-прогон и не опубликовал бы комментарий. Ни
`workflow_dispatch`, ни заглушки в комментарии для этого не нужны. Проверить
фактический итог можно командами `diagnose-pr-checks.mjs` и
`verify-recovery.mjs` из раздела ниже, а опубликованный текст комментария
хранится в `docs/testeb-67-task-comment.md`.


## Как проверить

```bash
# диагностика: код 1, пока у коммита нет успешных проверок (red)
node scripts/ci/diagnose-pr-checks.mjs \
  --repo sarnas-it/ebike-site --pr 62 \
  --sha 578e87b5a8587d6078aade92d26bd449448dd1d3

# после recovery: реальный успешный run + зелёные проверки + merge (green)
node scripts/ci/verify-recovery.mjs \
  --repo sarnas-it/ebike-site --pr 62 \
  --sha 578e87b5a8587d6078aade92d26bd449448dd1d3 \
  --run-id 42445204104

# критерии 2 и 3: CI на сдаваемом коммите PR #65 успешен, PR готов к автомержу
# (green). Workflow передаёт сюда TARGET_SHA события; вручную можно проверить
# конкретный коммит:
node scripts/ci/verify-rollout.mjs \
  --repo sarnas-it/ebike-site --pr 65 \
  --sha <сдаваемый head-коммит PR #65>

# диагностика PR #73 и проверка recovery. Без --sha проверяется актуальный
# head PR #73, поэтому уехавший head не подменяется старым SHA из инцидента.
# Без --run-id точный recovery-прогон берётся из commit status ci/recover.
node scripts/ci/diagnose-pr-checks.mjs \
  --repo sarnas-it/ebike-site --pr 73 \
  --sha 525b0d4803e03858cdb0bfce9cb90837bdf0473e
node scripts/ci/verify-recovery.mjs \
  --repo sarnas-it/ebike-site --pr 73
# фактический результат для PR #73:
#   workflow run: #42492889252 completed/success
#   merge commit: 6a14f53349e6f7da967e1bd1aded48c12deb82be
```

`verify-recovery.mjs` возвращает код 1, пока нет завершившегося успешного
recovery-прогона, у коммита нет зелёных check runs, нет `ci/recover`-статуса
со ссылкой на этот прогон или отсутствует merge commit. Это red-green гейт для
восстановления PR #62 и PR #73. Без `--sha` гейт сам берёт актуальный head PR,
а `--sha` позволяет явно закрепить коммит.

`verify-rollout.mjs` возвращает код 1, пока PR #65 не нацелен в `main`, не
готов к автомержу (открыт с конфликтами или закрыт без мержа), файла
`.github/workflows/ci.yml` нет в дереве head (или в `main` после мержа),
прогон `ci` на проверяемом коммите (`--sha`/`TARGET_SHA`) не успешен, в нём
нет полного набора job, он относится к другому SHA, а также если штатные
check runs этого прогона привязаны к другому коммиту или другому check suite.
Это red-green гейт именно для критериев «починка в PR #65 и CI на её
сдаваемом head успешен» и «PR готов к автомержу».

Ручной запуск recovery (если нужно повторить):

```bash
export GITHUB_TOKEN=<токен с правами contents:write, checks:write, statuses:write, pull-requests:write>
node scripts/ci/recover-pr.mjs \
  --repo sarnas-it/ebike-site --pr 62 \
  --sha 578e87b5a8587d6078aade92d26bd449448dd1d3 \
  --results <checks.json> --details-url <url запуска>

# обновить комментарий фактическими доказательствами (PR #62)
node scripts/ci/publish-evidence.mjs \
  --repo sarnas-it/ebike-site --pr 62 \
  --sha 578e87b5a8587d6078aade92d26bd449448dd1d3 \
  --run-id <id recovery-прогона> \
  --comment-file docs/testeb-63-comment.md \
  --comment-marker '<!-- testeb-63-recovery -->'

# PR #73: сначала разрешите актуальный head, затем проверьте и смержите именно его
node scripts/ci/resolve-pr-target.mjs \
  --repo sarnas-it/ebike-site --pr 73 \
  --expected-sha 525b0d4803e03858cdb0bfce9cb90837bdf0473e
node scripts/ci/recover-pr.mjs \
  --repo sarnas-it/ebike-site --pr 73 \
  --sha <resolved head sha> \
  --results <checks.json> --details-url <url запуска>

# обновить комментарий фактическими доказательствами (PR #73)
node scripts/ci/publish-evidence.mjs \
  --repo sarnas-it/ebike-site --pr 73 \
  --sha <resolved head sha> \
  --run-id <id recovery-прогона> \
  --comment-file docs/testeb-67-comment.md \
  --comment-marker '<!-- testeb-67-recovery -->'
```

Токен читается из `GITHUB_TOKEN`/`GH_TOKEN`, нигде не печатается и не
коммитится. Для чтения публичного репозитория токен не обязателен.

## Локальная проверка

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
bash scripts/ci/run-checks.sh .
```

`run-checks.sh` запускает все `scripts/ci/tests/*.test.mjs`. Red-green
доказательства:

* `scripts/ci/tests/evidence.test.mjs` — зелёные check runs без реального
  workflow run считаются red; упавший/незавершённый прогон, прогон чужого
  workflow, незамерженный PR и упавшие проверки тоже red; при зелёных
  доказательствах комментарий обновляется (PATCH), а не дублируется, и
  содержит ссылку на run/id, список проверок и merge commit; старый блок
  доказательств заменяется, а не дублируется; при red доказательствах ничего
  не публикуется. Зелёные check runs без `ci/recover`-статуса со ссылкой на
  recovery-прогон считаются red, а прогон, не связанный с этим статусом, не
  принимается.
* `scripts/ci/tests/resolve-pr-target.test.mjs` — red-green тест: recovery
  берёт актуальный head PR, даже если SHA из инцидента уже уехал, и помечает
  дрейф; PR без head SHA отклоняется.
* `scripts/ci/tests/finalize-evidence.test.mjs` — finalizer ждёт
  `completed`-прогон, не публикует упавший/незавершённый прогон, находит
  recovery-прогон по SHA триггера и не путает его с собственным прогоном
  финализатора, а при отсутствии recovery-прогона остаётся red; заменяет
  старый in-progress блок доказательств финальным `completed/success` с
  ссылкой на run и merge commit.
* `scripts/ci/tests/workflow-triggers.test.mjs` — фикстура без
  `pull_request` детектируется как сломанная; фикстуры recovery без `push`,
  нужных write-прав, точного checkout по `TARGET_SHA`/актуального head и
  `run-name` детектируются как сломанные; реальные `ci.yml`,
  `recover-pr62.yml`, `recover-pr73.yml` и `finalize-pr73-evidence.yml`
  проходят. Дополнительно проверяется отсутствие `paths`-фильтра,
  динамическое разрешение head вместо зашитого SHA, отказ recovery от
  хрупкого `workflow_dispatch` финализатора, отказ публиковать `in_progress`,
  кавычки вокруг `name`/`run-name` с `#73`, push-триггер и поиск
  recovery-прогона по SHA в финализаторе, исполнение PHP-контрактов плагина в
  CI, запуск rollout-гейта на PR #65 с `TARGET_SHA`, передача `--sha` в
  verify/publish, артефакт evidence и полный набор проверок в
  `run-checks.sh`.
* `scripts/ci/tests/recover-pr.test.mjs` — пустой/упавший набор проверок не
  даёт мержа, успешный даёт; проверяется, что check runs публикуются именно на
  целевой SHA, что recovery мержит разрешённый актуальный head после дрейфа,
  что при уехавшем во время прогона head или уже смерженном PR мержа нет, что
  комментарий идемпотентен и что существующий check run обновляется, а не
  дублируется.
* `scripts/ci/tests/run-checks.test.mjs` — здоровый checkout даёт зелёный
  результат, а падающий тест и битый PHP-синтаксис дают красный, то есть
  recovery не может «позеленить» сломанный коммит. Отдельный red-green тест
  кладёт синтаксически валидный, но падающий при исполнении
  `accounts-contract-test.php`: без группы `php-plugin-tests` он проходил бы
  только через `php -l`, а с ней прогон становится красным, то есть
  приёмочная PHP-команда действительно исполняется.
* `scripts/ci/tests/repair-pr-checks.test.mjs` — починка целится в head PR, а
  не в отдельную задачную ветку.
* `scripts/ci/tests/rollout.test.mjs` — открытый PR #65 с
  `mergeable=true` без конфликтов и полным зелёным прогоном `ci` на реальном
  head даёт green (готов к автомержу, мерж до ACCEPT не требуется); тот же PR
  после мержа тоже green. Конфликты/`mergeable=false`, закрытый без мержа PR,
  неверная базовая ветка, отсутствие `ci.yml` в дереве head, прогон на старом
  коммите (при другом текущем head) или от чужого workflow,
  упавший/незавершённый прогон и отсутствующий/упавший job дают red.
  Отдельно проверяется стандартность check runs: зелёные check runs с теми же
  именами, но из чужого check suite/другого run (синтетические через Checks
  API), отсутствующий или упавший штатный check run дают red. Отдельные
  red-green тесты закрывают замечание ревьюера: check run из другого check
  suite не принимается, даже если его `details_url` ссылается на проверяемый
  прогон `ci` (до исправления он проходил по ссылке), а check run, привязанный
  к другому коммиту (например, `7052bf2` вместо сдаваемого head), даёт red.
  Проверяется и выбор последнего прогона: новый упавший прогон не маскируется
  старым успешным. Evidence-комментарий привязан к реальному head, обновляется
  идемпотентно, содержит штатные check runs и merge commit и не публикуется при
  красных доказательствах или старом SHA.
* `scripts/ci/tests/workflow-triggers.test.mjs` дополнительно требует, чтобы
  `verify-ci-rollout` запускался на самом PR #65 (`pull_request`), фиксировал
  `TARGET_SHA` из `pull_request.head.sha` и передавал его через `--sha` и в
  `verify-rollout.mjs`, и в `publish-rollout-evidence.mjs`, а не проверял
  «какой-нибудь» текущий head. Для `push` коммит выбирается автоматически
  (merge commit после мержа), чтобы последующие push в `main` не превращали
  доказательство в красное.
