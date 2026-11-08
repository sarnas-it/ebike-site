# CI

## Причина `no_checks` по PR #68 (TESTEB-65)

Застрявший коммит `766eade4e49a86d4c14791da8d33e735948cfadb` (head ветки
`feat/ebike-site-testeb-29`) не содержит `.github/workflows` в своём дереве.
Это проверено по содержимому самого коммита: `git ls-tree` и
`GET /contents/.github/workflows` для этого SHA не находят каталог workflow, а
patch PR #68 меняет только файлы внутри `wp/**`.

Проблема не только в head: в базовой ветке `main` на момент постановки задачи
тоже не было `.github/workflows`. `main` тогда указывал на `efe1bc4` (мерж
PR #62), и его дерево также состояло только из `wp/**`; корневого `.github/`
не было. Для события `pull_request` GitHub ищет workflow-файл в дереве PR
(merge-дерево head + base). Ни в head, ни в base файла не было, поэтому
запускать было нечего — отсюда `no checks reported for the pull request`.

GitHub не запускает workflow задним числом: появление файла в другой ветке или
позже в `main` не создаёт прогон для уже существующего неизменённого head PR.
Нужен либо новый коммит в head (новое событие `pull_request`/`synchronize`),
либо отдельный прогон, который явно проверит актуальный head PR.

Проверенные данные:

* PR #68 — head `766eade4e49a86d4c14791da8d33e735948cfadb`, base `main`;
* в дереве `766eade4` нет `.github/workflows` (raw/contents возвращает `404`);
* в `main` на момент постановки (`efe1bc4`) не было `.github/workflows` и
  вообще не было корневых файлов, только `wp/**`;
* patch PR #68 добавляет и меняет только `wp/**`, workflow не добавляет;
* фильтров по путям и веткам в CI нет: триггер `pull_request` широкий, без
  `paths`;
* очередь раннеров и лимит минут не при чём: прогон для этого SHA вообще не
  создавался;
* allow-list интеграции содержит `sarnas-it/ebike-site`, иначе PR #68 не был
  бы создан.

## Вторая причина по PR #68 (TESTEB-65): падение recovery на самодиспатче финализатора

Первый recovery-прогон `Recover PR #68 checks` (run `42473326719`) успешно
опубликовал проверки и завершил отложенный мерж PR #68, но упал на последнем
шаге `Request completed evidence finalization`. Причина: recovery вызывал
`workflow_dispatch` для `finalize-pr68-evidence.yml`, а GitHub разрешает
диспатчить только workflow, который уже существует в базовой ветке. На ветке
починки финализатор есть, в `main` его ещё не было, поэтому dispatch вернул
«workflow not found». В результате на head ветки починки оставался красный
check `Recover PR #68 checks`, хотя проверки и мерж уже прошли.

Исправление: убрать самодиспатч. Recovery и finalizer запускаются одним и тем
же `push`; finalizer сам находит recovery-прогон по точному SHA триггера
(`--trigger-sha`), ждёт его завершения и только потом публикует доказательства.
Если передан `--trigger-sha`, `wait-and-publish-evidence.mjs` **не**
подставляет собственный `GITHUB_RUN_ID`: иначе финализатор ждал бы сам себя и
никогда не увидел бы recovery-прогон.

Recovery для застрявшего PR #68 описан в разделе ниже; он использует
`.github/workflows/recover-pr68.yml`, а доказательства публикует
`.github/workflows/finalize-pr68-evidence.yml` с комментарием
`docs/testeb-65-comment.md` и маркерами `<!-- testeb-65-evidence:start/end -->`.


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
либо отдельный прогон, который явно проверит актуальный head PR.

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

### 2. Recovery для уже застрявшего PR #73

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
  --repo sarnas-it/ebike-site --pr 73 \
  --sha 525b0d4803e03858cdb0bfce9cb90837bdf0473e

# после recovery: реальный успешный run + зелёные проверки + merge (green).
# Без --sha проверяется актуальный head PR #73, поэтому уехавший head не
# подменяется старым SHA из инцидента. Без --run-id точный recovery-прогон
# берётся из commit status ci/recover на проверяемом коммите.
node scripts/ci/verify-recovery.mjs \
  --repo sarnas-it/ebike-site --pr 73
# фактический результат для PR #73:
#   workflow run: #42492889252 completed/success
#   merge commit: 6a14f53349e6f7da967e1bd1aded48c12deb82be
```

`verify-recovery.mjs` возвращает код 1, пока нет завершившегося успешного
recovery-прогона, у коммита нет зелёных check runs, нет `ci/recover`-статуса
со ссылкой на этот прогон или отсутствует merge commit. Это red-green гейт для
восстановления PR #73. Без `--sha` гейт сам берёт актуальный head PR, а `--sha`
позволяет явно закрепить коммит.

Ручной запуск recovery (если нужно повторить). Сначала разрешите актуальный
head, затем проверьте и смержите именно его:

```bash
export GITHUB_TOKEN=<токен с правами contents:write, checks:write, statuses:write, pull-requests:write>
node scripts/ci/resolve-pr-target.mjs \
  --repo sarnas-it/ebike-site --pr 73 \
  --expected-sha 525b0d4803e03858cdb0bfce9cb90837bdf0473e
node scripts/ci/recover-pr.mjs \
  --repo sarnas-it/ebike-site --pr 73 \
  --sha <resolved head sha> \
  --results <checks.json> --details-url <url запуска>

# обновить комментарий фактическими доказательствами
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
  не публикуется.
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
  `pull_request` детектируется как сломанная; фикстура recovery без `push`,
  нужных write-прав, checkout актуального head и `run-name` с целевым PR
  детектируется как сломанная; реальные `ci.yml`, `recover-pr73.yml` и
  `finalize-pr73-evidence.yml` проходят. Дополнительно проверяется отсутствие
  `paths`-фильтра, динамическое разрешение head вместо зашитого SHA, отказ
  recovery от хрупкого `workflow_dispatch` финализатора, отказ публиковать
  `in_progress`, кавычки вокруг `name`/`run-name` с `#73`, push-триггер и
  поиск recovery-прогона по SHA в финализаторе, исполнение PHP-контрактов
  плагина в CI и полный набор проверок в `run-checks.sh`.
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

## Причина `unknown` по PR #78 (TESTEB-68)

Джоба `php-tests` прогона `ci` #42500219010 отработала успешно: theme-тесты
прошли, после них выполнился `Post job cleanup`. При этом у коммита остался
`in_progress`-дубликат check run с тем же именем `php-tests` — след отменённого
или перезапущенного предыдущего прогона (`concurrency.cancel-in-progress`).
Проверка статусов считала любой pending-дубликат блокирующим, поэтому мерж ждал
`php-tests` 30 минут и был отложен с классом `unknown`. Recovery из TESTEB-67
был зашит на PR #73, поэтому для #78 автоматического восстановления не было.

Исправление:

* `scripts/ci/lib.mjs` выбирает самый свежий check run для каждого имени (и
  самый свежий commit status для каждого контекста), поэтому старый pending не
  перекрывает завершённую успешную проверку. При публикации recovery
  завершаются все оставшиеся pending-дубликаты check runs.
* Если чужой check run нельзя обновить через PATCH (`403 Resource not
  accessible by integration`), recovery публикует новый завершённый check run,
  а не падает. Первый recovery-прогон #42504665607 упал именно на этом шаге и
  не дошёл до публикации `ci/recover` и мержа. Раньше `upsertCheckRun`
  пробрасывал ошибку PATCH, когда у проверки не было pending-дубликатов.
* `.github/workflows/ci.yml` не отменяет предыдущий прогон
  (`cancel-in-progress: false`), чтобы отменённые джобы не оставляли вечные
  `in_progress`.
* `.github/workflows/recover-pr.yml` — общий recovery после завершения `ci` и
  вручную. Он определяет PR по событию или коммиту, проверяет только зависшие
  или отсутствующие результаты (`scripts/ci/should-recover.mjs`) и не маскирует
  настоящие падения.
* `.github/workflows/recover-pr78.yml` — recovery текущего PR #78. Он берёт
  актуальный head через GitHub API, прогоняет полный `scripts/ci/run-checks.sh`
  и завершает отложенный мерж при зелёных проверках.
* `.github/workflows/finalize-pr78-evidence.yml` публикует итоговые
  доказательства только после завершения recovery-прогона.
* `scripts/ci/evidence.mjs` перечисляет в доказательствах только самый свежий
  check run для каждого имени, поэтому старый `in_progress`-дубликат не
  выглядит как незавершённая проверка рядом с зелёным результатом.

### Результат после починки (фактический)

* recovery-прогон
  [#42505520113](https://github.com/sarnas-it/ebike-site/actions/runs/42505520113)
  — `.github/workflows/recover-pr78.yml`, событие `push`,
  `status=completed`, `conclusion=success`, runner head
  `65899b710cb71e2dea0c5b3ec46dc3171fac5a4f` (ветка
  `feat/ebike-site-testeb-68`);
* на проверяемом head PR #78 `841470c19d9e62c1a6c4e4c402dff322c81fd747`
  опубликованы завершённые зелёные check runs `php-lint` (128807297687),
  `php-tests` (128807302983), `plugin-tests` (128807306499),
  `php-plugin-tests` (128807310353), `ci-repair-tests` (128807314799);
* commit status `ci/recover=success` на этом коммите ссылается на run
  #42505520113, а старый pending-дубликат `php-tests` `128791152777` больше не
  является authoritative;
* финализатор [Finalize PR #78 evidence
  #42505520131](https://github.com/sarnas-it/ebike-site/actions/runs/42505520131)
  завершился `completed/success`;
* отложенный мерж PR #78 выполнен без ручного вмешательства
  `2026-11-08T17:33:38Z`, merge commit
  `330a631e8655c0ae1f2c8d76a011ae319e90a0ac`.

Red-green проверка критериев приёмки:
`node scripts/ci/tests/pr78-recovery-evidence.test.mjs` держит инцидентный
снимок (упавший run #42504665607, PR не смержен) красным, а фактический
post-fix снимок (run #42505520113, merge `330a631...`) зелёным.

Проверка: `for f in wp/plugins/velotok-core/tests/*-test.php wp/themes/velotok/tests/*-test.php; do php "$f" || exit 1; done`.
