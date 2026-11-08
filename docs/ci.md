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

### Как подтверждаются критерии 2 и 3 (PR #65)

Трекер мержит PR #65 только после ACCEPT, поэтому требовать состояние
`merged: true` до приёмки нельзя: такой гейт был бы красным по определению и
ничего не доказывал. Доказательством служит не «будущий» мерж, а штатный
прогон `ci` на **актуальном head-коммите PR #65** (обычный workflow run и его
check runs, а не синтетические Check Runs API). Поэтому:

* `verify-ci-rollout` запускается на самом PR #65 (`pull_request`), а не только
  на `push` в `main`, и проверяет прогон `ci` на текущем head;
* гейт `scripts/ci/verify-rollout.mjs` зелёный, когда PR #65 открыт,
  `mergeable=true` и нет конфликтов с `main`, а последний прогон `ci` на его
  реальном head завершился успешно со всеми job; после мержа тот же гейт
  переключается на **merge commit** и требует успешный прогон `ci` уже на нём;
* успешный прогон на другом (например, более старом) коммите не принимается:
  SHA прогона сверяется с текущим head, поэтому зелёный прогон прошлой сдачи
  не может подтвердить новую;
* workflow `.github/workflows/ci.yml` читается из дерева head PR #65 до мержа и
  из `main` после него — именно оттуда GitHub берёт его для прогона.

Единственный надёжный момент, когда прогон `ci` на сдаваемом коммите уже
существует, — это сам PR #65: любой новый push создаёт событие
`pull_request`/`synchronize`, а значит и новый прогон на новом head. После
мержа очередь трекера создаёт `push` в `main`, поэтому появляется прогон уже на
merge commit. Ссылка на run и его `head_sha` фиксируются в check run
`verify-ci-rollout` и в выводе гейта, поэтому приёмка всегда сверяется с
фактическим проверяемым коммитом (head до мержа, merge commit после), а не с
захардкоженным SHA из документации.

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
проверок только расширен, ни одна проверка не отключена.

Готовность починки (критерии 2 и 3) и успешный прогон CI именно на реальном
head-коммите PR #65 проверяются red-green гейтом
`scripts/ci/verify-rollout.mjs`. Гейт возвращает код 1, пока не выполнено хотя
бы одно условие:

* PR [#65](https://github.com/sarnas-it/ebike-site/pull/65) нацелен в `main` и
  либо открыт с `mergeable=true` без конфликтов, либо уже смержен (тогда есть
  merge commit);
* `.github/workflows/ci.yml` присутствует в дереве head PR #65 (до мержа) или в
  `main` (после мержа);
* последний прогон `ci` на **текущем head-коммите** PR #65 завершился успешно
  (а после мержа — на merge commit, который создал `push` в `main`);
* в прогоне присутствуют все job: `php-lint`, `php-tests`, `plugin-tests`,
  `ci-repair-tests`, и каждая завершилась успешно;
* на этом же проверяемом коммите есть **штатные check runs** этих job,
  привязанные к check suite проверяемого прогона `ci`. Зелёные check runs, созданные через
  Checks API в обход workflow (например, recovery-скриптом), гейт не принимает:
  они не принадлежат check suite реального прогона `ci` и не являются
  доказательством того, что workflow отработал на этом коммите.

`.github/workflows/verify-ci-rollout.yml` запускает этот гейт на самом PR #65
(`pull_request`), на каждый `push` в `main` и вручную. Прогон на `pull_request`
и есть штатное доказательство критерия 2: он прикрепляет обычный check run
`verify-ci-rollout` к актуальному head PR #65 и проверяет прогон `ci` на этом
же SHA. Мерж до ACCEPT не требуется, потому что его выполняет очередь трекера;
после мержа гейт остаётся зелёным и дополнительно подтверждает прогон `ci` и
штатные check runs уже на merge commit.
Успешный прогон на другом (старом) SHA гейт не принимает, поэтому зелёный
прогон предыдущей сдачи не может подтвердить новый коммит. SHA нигде не
захардкожен: гейт каждый раз берёт актуальный head из PR #65, поэтому доказательство
всегда относится именно к тому коммиту, который сдаётся.

После зелёного гейта шаг `publish-rollout-evidence.mjs` создаёт или обновляет
один идемпотентный комментарий на PR #65 (`<!-- testeb-63-rollout -->`) со
ссылкой на штатный run, его `head_sha`, списком job, штатными check runs,
состоянием `mergeable`/конфликтов и merge commit (если PR уже смержен).
Комментарий привязан к фактическому head на момент прогона и не публикуется,
пока доказательства красные. До ACCEPT PR #65 ещё не смержен, поэтому
`merge commit` в нём `none`; после мержа тот же workflow перезапускается на
`main`, гейт читает workflow из базовой ветки и записывает фактический merge
commit.

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

# критерии 2 и 3: CI на реальном head PR #65 успешен, PR готов к автомержу (green)
node scripts/ci/verify-rollout.mjs \
  --repo sarnas-it/ebike-site --pr 65
```

`verify-recovery.mjs` возвращает код 1, пока нет завершившегося успешного
recovery-прогона, у коммита нет зелёных check runs или отсутствует merge
commit. Это red-green гейт для восстановления PR #62.

`verify-rollout.mjs` возвращает код 1, пока PR #65 не нацелен в `main`, не
готов к автомержу (открыт с конфликтами или закрыт без мержа), файла
`.github/workflows/ci.yml` нет в дереве head (или в `main` после мержа),
последний прогон `ci` на текущем head не успешен, в нём нет полного набора
job или этот прогон относится к другому SHA. Это red-green гейт именно для
критериев «починка в PR #65 и CI на её реальном head успешен» и «PR готов к
автомержу».

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
* `scripts/ci/tests/rollout.test.mjs` — открытый PR #65 с
  `mergeable=true` без конфликтов и полным зелёным прогоном `ci` на реальном
  head даёт green (готов к автомержу, мерж до ACCEPT не требуется); тот же PR
  после мержа тоже green. Конфликты/`mergeable=false`, закрытый без мержа PR,
  неверная базовая ветка, отсутствие `ci.yml` в дереве head, прогон на старом
  коммите (при другом текущем head) или от чужого workflow,
  упавший/незавершённый прогон и отсутствующий/упавший job дают red.
  Отдельно проверяется стандартность check runs: зелёные check runs с теми же
  именами, но из чужого check suite/другого run (синтетические через Checks
  API), отсутствующий или упавший штатный check run дают red. Проверяется и
  выбор последнего прогона: новый упавший прогон не маскируется старым
  успешным. Evidence-комментарий привязан к реальному head, обновляется
  идемпотентно, содержит штатные check runs и merge commit и не публикуется при
  красных доказательствах или старом SHA.
* `scripts/ci/tests/workflow-triggers.test.mjs` дополнительно требует, чтобы
  `verify-ci-rollout` запускался на самом PR #65 (`pull_request`) и проверял
  его реальный head, а не только `push` в `main`.
