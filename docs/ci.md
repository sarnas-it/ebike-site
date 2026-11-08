# CI

## Причина `no_checks` по PR #71 (TESTEB-66)

Застрявший коммит `19c4f0dbfb0b14eb7b9ef5fc6ecd6207769e5008` (head ветки
`feat/ebike-site-testeb-2`) не содержит `.github/workflows` в своём дереве.
Это проверено по содержимому самого коммита: `git ls-tree` и
`GET /contents/.github/workflows` для этого SHA не находят каталог workflow, а
patch PR #71 меняет файлы внутри `wp/**` и добавляет корневой `verify.log`, но workflow не добавляет.

Проблема не только в head: в базовой ветке `main` на момент постановки задачи
тоже не было `.github/workflows`. `main` тогда указывал на `259f59dc6f` (мерж
PR #68), и его дерево также состояло только из `wp/**`; корневого `.github/`
не было. Для события `pull_request` GitHub ищет workflow-файл в дереве PR
(merge-дерево head + base). Ни в head, ни в base файла не было, поэтому
запускать было нечего — отсюда `no checks reported for the pull request`.

GitHub не запускает workflow задним числом: появление файла в другой ветке или
позже в `main` не создаёт прогон для уже существующего неизменённого head PR.
Нужен либо новый коммит в head (новое событие `pull_request`/`synchronize`),
либо отдельный прогон, который явно проверит актуальный head PR.

Проверенные данные:

* PR #71 — head `19c4f0dbfb0b14eb7b9ef5fc6ecd6207769e5008`, base `main`;
* в дереве `19c4f0db` нет `.github/workflows` (raw/contents возвращает `404`);
* в `main` на момент постановки (`259f59dc6f`, мерж PR #68) не было `.github/workflows` и
  вообще не было корневых файлов, только `wp/**`;
* patch PR #71 добавляет и меняет `wp/**` и `verify.log`, workflow не добавляет.

Предварительная проверка целевого коммита: клон ветки
`feat/ebike-site-testeb-2` на `19c4f0db` прогнан через
`bash scripts/ci/run-checks.sh <checkout>`; `php-lint`, `php-tests` и
`plugin-tests` завершились успешно. Значит, после публикации этих же проверок
recovery-workflow сможет завершить отложенный мерж без ручного вмешательства.

Остальные возможные причины исключены:

* GitHub Actions в репозитории включены: PR из соседних задач, в чьих ветках
  есть `.github/workflows/ci.yml`, получают прогоны;
* фильтров по путям и веткам в CI нет: триггер `pull_request` широкий, без
  `paths`;
* очередь раннеров и лимит минут не при чём: прогон для этого SHA вообще не
  создавался;
* allow-list интеграции содержит `sarnas-it/ebike-site`, иначе PR #71 не был
  бы создан.

## Что исправлено

### 1. Обычный CI для будущих PR

`.github/workflows/ci.yml` запускается:

* на каждом `pull_request` без фильтров по путям и веткам;
* на `push` в `main`;
* вручную через `workflow_dispatch`.

Проверки: `php-lint`, `php-tests`, `plugin-tests`, `ci-repair-tests`. Набор
проверок только расширен, ни одна проверка не отключена. `ci-repair-tests`
запускает все `scripts/ci/tests/*.test.mjs`, поэтому новые red-green тесты не
могут быть пропущены.

После мержа этой починки в `main` файл `.github/workflows/ci.yml` окажется в
базовой ветке, и новые PR будут получать реальные прогоны.

### 2. Recovery для уже застрявшего PR #71

`.github/workflows/recover-pr71.yml` запускается на `push` и вручную и:

1. разрешает **актуальный head PR #71** через GitHub API
   (`scripts/ci/resolve-pr-target.mjs`) и выкачивает именно его
   (`git rev-parse HEAD` сверяется с разрешённым SHA). SHA из инцидента
   `19c4f0dbfb0b14eb7b9ef5fc6ecd6207769e5008` остаётся только в
   `INCIDENT_SHA` для аудита: recovery не привязан к нему и не пропускает
   мерж, если head уехал;
2. прогоняет на разрешённом коммите те же проверки через общий
   `scripts/ci/run-checks.sh` (`php-lint`, `php-tests`, `plugin-tests`);
3. публикует результаты как check runs с именами `php-lint`, `php-tests`,
   `plugin-tests` и commit status `ci/recover` **на этот же SHA**;
4. завершает отложенный мерж PR #71 (если он ещё открыт и head всё ещё равен
   проверенному коммиту). Если head уехал между проверкой и мержем, мерж
   безопасно пропускается; следующий push в PR-ветку запускает recovery уже
   по новому head;
5. в конце запускает `scripts/ci/request-evidence-finalize.mjs`, который
   диспатчит отдельный workflow `Finalize PR #71 evidence`
   (`.github/workflows/finalize-pr71-evidence.yml`). Тот ждёт завершения
   именно этого recovery-прогона и только потом вызывает
   `scripts/ci/publish-evidence.mjs`: проверяет реальный успешный workflow
   run, зелёные проверки на проверенном SHA и наличие merge commit, после чего
   создаёт **или обновляет** один идемпотентный комментарий к PR #71 с
   фактическими ссылками на run, проверки и merge commit. Поэтому в
   комментарий никогда не попадает `in_progress` как итоговый результат.
   Черновик не публикуется «как есть»: блок доказательств между маркерами
   `<!-- testeb-66-evidence:start/end -->` заменяется данными из API, поэтому
   старые in-progress доказательства не остаются и не дублируются.

`name` и `run-name` workflow взяты в кавычки: без них `#71` в YAML считается
началом комментария, и GitHub показывал бы прогон как «Recover PR». Теперь имя
и run-name однозначно указывают на PR #71, а проверяемый SHA, ветка и факт
дрейфа пишутся в summary прогона.

Повторные запуски идемпотентны: существующие check runs обновляются,
комментарий обновляется, а не дублируется, уже смерженный PR повторно не
мержится, а уехавший head не мержится вслепую.

### Фактический результат recovery

Итог recovery появится в комментарии к PR #71 после первого запуска
workflow. Финальный комментарий публикует только `Finalize PR #71 evidence`,
когда recovery-прогон завершился успешно, проверки на проверенном SHA зелёные,
а PR смержен. Ссылка на run, список проверок и merge commit подставляются из
GitHub API, поэтому в `docs/testeb-66-comment.md` заранее не записываются
вымышленные run id и merge commit.

## Как проверить

```bash
# диагностика: код 1, пока у коммита нет успешных проверок (red)
node scripts/ci/diagnose-pr-checks.mjs \
  --repo sarnas-it/ebike-site --pr 71 \
  --sha 19c4f0dbfb0b14eb7b9ef5fc6ecd6207769e5008

# после recovery: реальный успешный run + зелёные проверки + merge (green).
# Без --sha проверяется актуальный head PR #71, поэтому уехавший head не
# подменяется старым SHA из инцидента.
node scripts/ci/verify-recovery.mjs \
  --repo sarnas-it/ebike-site --pr 71 \
  --run-id <id recovery-прогона>
```

`verify-recovery.mjs` возвращает код 1, пока нет завершившегося успешного
recovery-прогона, у коммита нет зелёных check runs или отсутствует merge
commit. Это red-green гейт для восстановления PR #71. Без `--sha` гейт сам
берёт актуальный head PR, а `--sha` позволяет явно закрепить коммит.

Ручной запуск recovery (если нужно повторить). Сначала разрешите актуальный
head, затем проверьте и смержите именно его:

```bash
export GITHUB_TOKEN=<токен с правами contents:write, checks:write, statuses:write, pull-requests:write>
node scripts/ci/resolve-pr-target.mjs \
  --repo sarnas-it/ebike-site --pr 71 \
  --expected-sha 19c4f0dbfb0b14eb7b9ef5fc6ecd6207769e5008
node scripts/ci/recover-pr.mjs \
  --repo sarnas-it/ebike-site --pr 71 \
  --sha <resolved head sha> \
  --results <checks.json> --details-url <url запуска>

# обновить комментарий фактическими доказательствами
node scripts/ci/publish-evidence.mjs \
  --repo sarnas-it/ebike-site --pr 71 \
  --sha <resolved head sha> \
  --run-id <id recovery-прогона> \
  --comment-file docs/testeb-66-comment.md \
  --comment-marker '<!-- testeb-66-recovery -->'
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
  `completed`-прогон, не публикует упавший/незавершённый прогон и заменяет
  старый in-progress блок доказательств финальным `completed/success` с
  ссылкой на run и merge commit.
* `scripts/ci/tests/workflow-triggers.test.mjs` — фикстура без
  `pull_request` детектируется как сломанная; фикстура recovery без `push`,
  нужных write-прав, checkout актуального head, `run-name` с целевым PR и
  вызова финализатора доказательств детектируется как сломанная; реальные
  `ci.yml`, `recover-pr71.yml` и `finalize-pr71-evidence.yml` проходят.
  Дополнительно проверяется отсутствие `paths`-фильтра, динамическое
  разрешение head вместо зашитого SHA, отказ recovery публиковать
  `in_progress`, кавычки вокруг `name`/`run-name` с `#71` и полный набор
  проверок в `run-checks.sh`.
* `scripts/ci/tests/recover-pr.test.mjs` — пустой/упавший набор проверок не
  даёт мержа, успешный даёт; проверяется, что check runs публикуются именно на
  целевой SHA, что recovery мержит разрешённый актуальный head после дрейфа,
  что при уехавшем во время прогона head или уже смерженном PR мержа нет, что
  комментарий идемпотентен и что существующий check run обновляется, а не
  дублируется.
* `scripts/ci/tests/run-checks.test.mjs` — здоровый checkout даёт зелёный
  результат, а падающий тест и битый PHP-синтаксис дают красный, то есть
  recovery не может «позеленить» сломанный коммит.
* `scripts/ci/tests/repair-pr-checks.test.mjs` — починка целится в head PR, а
  не в отдельную задачную ветку.
