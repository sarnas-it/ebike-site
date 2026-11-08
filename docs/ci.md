# CI

## Причина `no_checks` по PR #62 (TESTEB-63)

Застрявший коммит `578e87b5a8587d6078aade92d26bd449448dd1d3` (head ветки
`feat/ebike-site-testeb-1`) не содержит `.github/workflows` в своём дереве. В
момент открытия и последнего push этого PR базовая ветка `main` тоже была
пустой (`40134b5`, `chore: empty repository`) и workflow в ней не было. Для
события `pull_request` GitHub ищет workflow-файл в дереве PR (merge-дерево:
head + base). Ни в head, ни в base файла не было, поэтому запускать было
нечего — отсюда `no checks reported for the pull request`.

GitHub не запускает workflow задним числом: появление файла в другой ветке или
позже в `main` не создаёт прогон для уже существующего неизменённого head PR.
Нужен либо новый коммит в head (новое событие `pull_request`/`synchronize`),
либо отдельный прогон, который явно проверит старый SHA.

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

## Фактический результат восстановления PR #62

Постоянные доказательства, снятые с GitHub после recovery-прогона:

| Что | Значение |
| --- | --- |
| PR #62 | `closed`, `merged: true`, `merged_at: 2026-11-08T04:03:25Z` |
| Merge commit | `efe1bc4dac551dc870cec68aff16a973e095dd4f` |
| Recovery workflow run | [#42445204104](https://github.com/sarnas-it/ebike-site/actions/runs/42445204104) — job `recover` завершился успешно |
| Проверки на `578e87b` | `php-lint=success`, `php-tests=success`, `plugin-tests=success` (3 check runs + commit status `ci/recover=success`) |
| CI на коммите починки `e4b76df` | [#42445208871](https://github.com/sarnas-it/ebike-site/actions/runs/42445208871) — все job (`php-lint`, `php-tests`, `plugin-tests`, `ci-repair-tests`) успешны |

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

Проверки: `php-lint`, `php-tests`, `plugin-tests`, `ci-repair-tests`. Набор
проверок только расширен, ни одна проверка не отключена. После мержа PR #65
файл попадает в `main`, поэтому новые PR больше не останутся без прогонов.

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

Повторные запуски идемпотентны: существующие check runs обновляются,
комментарий обновляется, а не дублируется, уже смерженный PR повторно не
мержится, а уехавший head не мержится вслепую.

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
```

`verify-recovery.mjs` возвращает код 1, пока нет завершившегося успешного
recovery-прогона, у коммита нет зелёных check runs или отсутствует merge
commit. Это red-green гейт для критериев 2 и 3.

Ручной запуск recovery (если нужно повторить):

```bash
export GITHUB_TOKEN=<токен с правами contents:write, checks:write, statuses:write, pull-requests:write>
node scripts/ci/recover-pr.mjs \
  --repo sarnas-it/ebike-site --pr 62 \
  --sha 578e87b5a8587d6078aade92d26bd449448dd1d3 \
  --results <checks.json> --details-url <url запуска>

# обновить комментарий фактическими доказательствами
node scripts/ci/publish-evidence.mjs \
  --repo sarnas-it/ebike-site --pr 62 \
  --sha 578e87b5a8587d6078aade92d26bd449448dd1d3 \
  --run-id <id recovery-прогона> \
  --comment-file docs/testeb-63-comment.md \
  --comment-marker '<!-- testeb-63-recovery -->'
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
  содержит ссылку на run, список проверок и merge commit; при red
  доказательствах ничего не публикуется.
* `scripts/ci/tests/workflow-triggers.test.mjs` — фикстура без
  `pull_request` детектируется как сломанная; фикстура recovery без `push`,
  нужных write-прав, точного checkout по `TARGET_SHA`, `run-name` с целевым
  SHA и вызова `publish-evidence.mjs` детектируется как сломанная; реальные
  `ci.yml` и `recover-pr62.yml` проходят. Дополнительно проверяется отсутствие
  `paths`-фильтра и полный набор проверок в `run-checks.sh`.
* `scripts/ci/tests/recover-pr.test.mjs` — пустой/упавший набор проверок не
  даёт мержа, успешный даёт; проверяется, что check runs публикуются именно на
  целевой SHA, что при уехавшем head или уже смерженном PR мержа нет, что
  комментарий идемпотентен и что существующий check run обновляется, а не
  дублируется.
* `scripts/ci/tests/run-checks.test.mjs` — здоровый checkout даёт зелёный
  результат, а падающий тест и битый PHP-синтаксис дают красный, то есть
  recovery не может «позеленить» сломанный коммит.
* `scripts/ci/tests/repair-pr-checks.test.mjs` — починка целится в head PR, а
  не в отдельную задачную ветку.
