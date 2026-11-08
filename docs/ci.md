# CI

## Причина `no_checks` по PR #62 (TESTEB-63)

Коммит `578e87b5a8587d6078aade92d26bd449448dd1d3` (head ветки
`feat/ebike-site-testeb-1`) не содержит каталога `.github/workflows` в своём
дереве, поэтому событие `pull_request` не могло создать ни одного прогона.
GitHub API для этого SHA возвращает:

* `GET /repos/sarnas-it/ebike-site/commits/578e87b.../check-runs` —
  `total_count: 0`;
* `GET /repos/sarnas-it/ebike-site/commits/578e87b.../status` —
  `total_count: 0`, состояние `pending`;
* `GET /repos/sarnas-it/ebike-site/actions/runs?head_sha=578e87b...` —
  `total_count: 0`.

Запускать было нечего — это и есть причина состояния
`no checks reported for the pull request`.

Остальные возможные причины исключены:

* GitHub Actions в репозитории включены: workflow `ci` имеет состояние
  `active`, а соседние PR в это же время получают успешные прогоны;
* фильтров по путям и веткам в CI нет: триггер `pull_request` широкий;
* очередь раннеров и лимит минут не при чём: job на `ubuntu-latest`
  завершается за секунды, как видно по успешным прогонам соседних PR;
* allow-list интеграции содержит `sarnas-it/ebike-site`: PR #62 создан через
  интеграцию, и её токен открывает PR в этом репозитории.

### Где ошиблась предыдущая попытка

Прошлая сдача добавила workflow коммитом `02c1fe3` только в ветку
`feat/ebike-site-testeb-63` (PR #65). Head PR #62 (`578e87b`) при этом не
изменился, а GitHub не запускает workflow задним числом, поэтому:

* у GitHub не появилось повода запускать `pull_request` для PR #62;
* workflow из чужой ветки не попал в дерево `578e87b`;
* отложенный мерж PR #62 не получил ни проверок, ни нового head.

Поэтому недостаточно «где-то» добавить workflow: нужно проверить именно
застрявший коммит и привязать результат к нему.

## Что исправлено

Исправление состоит из двух частей.

### 1. Обычный CI для будущих PR

`.github/workflows/ci.yml` запускается:

* на каждом `pull_request` без фильтров по путям и веткам;
* на `push` в `main`;
* вручную через `workflow_dispatch`.

Проверки: `php-lint`, `php-tests`, `plugin-tests`, `ci-repair-tests`. Набор
проверок только расширен, ни одна проверка не отключена.

### 2. Recovery для уже застрявшего PR #62

`.github/workflows/recover-pr62.yml` запускается на `push` (то есть на push
этой сдачи) и вручную через `workflow_dispatch` и:

1. выкачивает **ровно коммит**
   `578e87b5a8587d6078aade92d26bd449448dd1d3` (`git rev-parse HEAD`
   сверяется с `TARGET_SHA`);
2. прогоняет на нём те же проверки через общий
   `scripts/ci/run-checks.sh` (`php-lint`, `php-tests`, `plugin-tests`);
3. публикует результаты как check runs с именами `php-lint`, `php-tests`,
   `plugin-tests` и commit status `ci/recover` **на этот же SHA** через
   Checks/Statuses API (у job есть `checks: write` и `statuses: write`);
4. постит один идемпотентный комментарий к PR #62 из
   `docs/testeb-63-comment.md`;
5. завершает отложенный мерж PR #62 (`squash`, затем `merge`/`rebase`), если
   все проверки зелёные, PR ещё открыт и его head всё ещё равен проверенному
   коммиту.

Так проверки появляются у старого SHA, а мерж выполняется автоматически, без
ручного вмешательства. Повторные запуски идемпотентны: существующие check runs
обновляются, комментарий не дублируется, уже смерженный PR повторно не
мержится, а уехавший head не мержится вслепую.

Обычный `ci.yml` при этом нужен, чтобы после мержа main больше не оставался без
workflow и новые PR не повторяли инцидент.

## Как проверить

```bash
# диагностика: код 1, пока у коммита нет успешных проверок (red)
node scripts/ci/diagnose-pr-checks.mjs \
  --repo sarnas-it/ebike-site --pr 62 \
  --sha 578e87b5a8587d6078aade92d26bd449448dd1d3

# после recovery: проверки на SHA зелёные, PR смержен (green)
node scripts/ci/verify-recovery.mjs \
  --repo sarnas-it/ebike-site --pr 62 \
  --sha 578e87b5a8587d6078aade92d26bd449448dd1d3
```

`verify-recovery.mjs` возвращает код 1, пока PR не смержен, у коммита нет
зелёных check runs или отсутствует merge commit. Это red-green гейт для
критериев 2 и 3.

Ручной запуск recovery (если нужно повторить):

```bash
export GITHUB_TOKEN=<токен с правами contents:write, checks:write, statuses:write, pull-requests:write>
node scripts/ci/recover-pr.mjs \
  --repo sarnas-it/ebike-site --pr 62 \
  --sha 578e87b5a8587d6078aade92d26bd449448dd1d3 \
  --results <checks.json> --details-url <url запуска>
```

Токен читается из `GITHUB_TOKEN`/`GH_TOKEN`, нигде не печатается и не
коммитится. Для чтения публичного репозитория токен не обязателен.

## Локальная проверка

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
bash scripts/ci/run-checks.sh .
node scripts/ci/tests/repair-pr-checks.test.mjs
node scripts/ci/tests/recover-pr.test.mjs
node scripts/ci/tests/run-checks.test.mjs
node scripts/ci/tests/workflow-triggers.test.mjs
```

Red-green доказательства:

* `scripts/ci/tests/workflow-triggers.test.mjs` — фикстура без `pull_request`
  детектируется как сломанная; фикстура recovery без `push`,
  `checks: write`, `statuses: write`, `pull-requests: write`, точного checkout
  по `TARGET_SHA` и вызова общих проверок детектируется как сломанная; реальные
  `ci.yml` и `recover-pr62.yml` проходят. Дополнительно проверяется отсутствие
  `paths`-фильтра и полный набор проверок в `run-checks.sh`.
* `scripts/ci/tests/recover-pr.test.mjs` — пустой/упавший набор проверок не
  даёт мержа, успешный даёт; проверяется, что check runs публикуются именно на
  целевой SHA, что при уехавшем head или уже смерженном PR мержа нет, что
  комментарий идемпотентен и что существующий check run обновляется, а не
  дублируется. Тесты падают на коде без recovery-логики.
* `scripts/ci/tests/run-checks.test.mjs` — здоровый checkout даёт зелёный
  результат, а падающий тест и битый PHP-синтаксис дают красный, то есть
  recovery не может «позеленить» сломанный коммит.
* `scripts/ci/tests/repair-pr-checks.test.mjs` — починка целится в head PR, а
  не в отдельную задачную ветку.

## Комментарий к задаче TESTEB-63

Текст готов и лежит в `docs/testeb-63-comment.md`. Recovery-workflow публикует
его как подтверждённый комментарий к PR #62
(<https://github.com/sarnas-it/ebike-site/pull/62>) при первом же запуске.
