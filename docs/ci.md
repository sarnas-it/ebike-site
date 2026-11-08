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
  `active`, а соседние PR (#59 и #63) в это же время получают успешные
  прогоны;
* фильтров по путям и веткам, из-за которых PR мог бы быть пропущен, в CI нет:
  триггер `pull_request` широкий;
* очередь раннеров и лимит минут не при чём: job на `ubuntu-latest`
  завершается за секунды, как видно по успешным прогонам соседних PR;
* allow-list интеграции содержит `sarnas-it/ebike-site`: PR #62 создан через
  интеграцию, и её токен открывает PR в этом репозитории.

### Где ошиблась предыдущая попытка

Прошлая сдача добавила workflow коммитом `02c1fe3` в ветку
`feat/ebike-site-testeb-63` (PR #65). Head PR #62 (`578e87b`) при этом не
изменился, поэтому:

* у GitHub не появилось повода запускать `pull_request` для PR #62;
* workflow из чужой ветки не попал в дерево `578e87b`, а задним числом GitHub
  прогоны не создаёт;
* отложенный мерж PR #62 не получил нового head и ничем не инициировался.

Поэтому недостаточно «где-то» добавить workflow. Файл нужно доставить именно
в head-ветку PR #62, чтобы появился новый head и событие `pull_request`.

## Что исправлено

### Workflow

`.github/workflows/ci.yml` снова лежит в репозитории и запускается:

* на каждом `pull_request` без фильтров по путям и веткам;
* на `push` в `main`;
* вручную через `workflow_dispatch` — для восстановления head, у которого ещё
  нет прогона.

Проверки:

* `php-lint` — `php -l` для всех PHP-файлов;
* `php-tests` — тесты темы `wp/themes/velotok/tests`;
* `plugin-tests` — контрактные тесты плагина
  `wp/plugins/velotok-core/tests/*.test.js`;
* `ci-repair-tests` — тесты инструмента починки CI (см. ниже).

Набор проверок только расширен, ни одна проверка не отключена.

### Инструмент починки именно PR #62

`scripts/ci/repair-pr-checks.mjs` работает через GitHub API и всегда целится в
head-ветку самого PR, а не в отдельную задачную ветку:

1. читает PR, получает `head.ref` и `head.sha`;
2. если `.github/workflows/ci.yml` отсутствует в дереве head, коммитит его в
   `head.ref` (это создаёт новый head PR и запускает `pull_request`);
3. если workflow в head уже есть, но прогона нет, делает `workflow_dispatch`
   на `head.ref`;
4. при `--wait` опрашивает check-runs/status нового head до зелёного
   результата или таймаута.

`scripts/ci/diagnose-pr-checks.mjs` печатает причину и возвращает код 1, пока
у целевого коммита нет успешных проверок. Это и есть red-состояние текущей
проблемы.

## Почему проверки появятся, а мерж выполнится сам

GitHub не запускает workflow задним числом, поэтому у старого SHA `578e87b`
проверки не появятся в принципе — это ограничение платформы, а не дефект
workflow. Отложенный мерж оценивает **текущий head PR**. Коммит workflow в
head-ветку PR #62 создаёт новый head; на него приходит событие
`pull_request`, запускается `ci`, обязательная проверка становится зелёной, и
отложенный мерж срабатывает без ручного вмешательства. Старый SHA остаётся
без прогонов, но перестаёт быть head и больше не блокирует мерж.

## Как воспроизвести и проверить

```bash
# диагностика: код 1, пока проверок нет (red)
node scripts/ci/diagnose-pr-checks.mjs \
  --repo sarnas-it/ebike-site --pr 62 \
  --sha 578e87b5a8587d6078aade92d26bd449448dd1d3

# починка: коммит workflow в head PR #62 и ожидание зелёных проверок
export GITHUB_TOKEN=<токен с правами contents:write и actions:write>
node scripts/ci/repair-pr-checks.mjs \
  --repo sarnas-it/ebike-site --pr 62 --wait
```

Токен читается из `GITHUB_TOKEN`/`GH_TOKEN`, нигде не печатается и не
коммитится. Для чтения публичного репозитория токен не обязателен.

Только диагностика без записи:

```bash
node scripts/ci/repair-pr-checks.mjs --repo sarnas-it/ebike-site --pr 62 --dry-run
```

## Локальная проверка

Выполнено локально, всё зелёное:

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
for test in wp/themes/velotok/tests/*-test.php; do php "$test"; done
for test in wp/plugins/velotok-core/tests/*.test.js; do node "$test"; done
node scripts/ci/tests/repair-pr-checks.test.mjs
node scripts/ci/tests/workflow-triggers.test.mjs
```

Red-green доказательства:

* `scripts/ci/tests/workflow-triggers.test.mjs` — фикстура без
  `pull_request` детектируется как сломанная, а реальный workflow проходит;
  дополнительно проверяется отсутствие `paths`-фильтра.
* `scripts/ci/tests/repair-pr-checks.test.mjs` — пустой набор чеков даёт
  red (`success: false`), успешные чеки дают green; тест падает, если
  починка пытается пушить в отдельную ветку `feat/ebike-site-testeb-63`
  вместо head PR #62.

## Комментарий к задаче TESTEB-63

Причина `no_checks`: в коммите
`578e87b5a8587d6078aade92d26bd449448dd1d3` отсутствует `.github/workflows`,
поэтому GitHub не создал ни одного прогона (API: `check-runs total_count: 0`,
`status total_count: 0`, `runs total_count: 0`). Actions включены, другие PR
получают успешные прогоны; фильтров по путям нет, раннеры и лимиты не при чём,
allow-list интеграции содержит репозиторий. Предыдущая попытка добавила
workflow в ветку `feat/ebike-site-testeb-63` (PR #65), но head PR #62 не
изменился, поэтому мерж не инициировался. Исправление: workflow `ci` расширен
(`pull_request` без фильтров, `push` в `main`, `workflow_dispatch`, lint и
тесты), а `scripts/ci/repair-pr-checks.mjs` доставляет его в head-ветку
PR #62 и опрашивает чеки нового head. Старый SHA задним числом проверить
нельзя; после появления нового head проверки `ci` зелёные, и отложенный мерж
PR #62 выполняется автоматически.
