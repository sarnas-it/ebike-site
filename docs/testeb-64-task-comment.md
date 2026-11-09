# Комментарий к задаче TESTEB-64 (запись)

<!-- testeb-64-task-comment -->

## Причина

`no_checks` по PR sarnas-it/ebike-site#60. Застрявший коммит
`b1b0871d6ebb4f7ce25a987c956c7b1431eef379` не содержит
`.github/workflows` в своём дереве, а в базовой ветке `main` на момент
открытия и последнего push workflow тоже не было (`efe1bc4` состоял только из
`wp/**`). Для события `pull_request` GitHub ищет workflow в merge-дереве head +
base; ни в head, ни в base файла не было, поэтому прогонов не создалось.
Actions были включены, фильтров по путям не было, очередь раннеров и лимиты
минут ни при чём, allow-list интеграции содержит репозиторий.

## Что исправлено

* `.github/workflows/ci.yml` запускается на каждом `pull_request`, на `push` в
  `main` и по `workflow_dispatch`; `paths`-фильтра нет.
* `.github/workflows/recover-pr60.yml` разрешает актуальный head PR #60,
  выкачивает именно его, прогоняет общий набор проверок
  (`php-lint`, `php-tests`, `php-plugin-tests`, `plugin-tests`,
  `ci-repair-tests`), публикует check runs на этот SHA и завершает отложенный
  мерж. Коммит `b1b0871d6ebb4f7ce25a987c956c7b1431eef379` сохранён только как
  `INCIDENT_SHA` для аудита.
* `.github/workflows/finalize-pr60-evidence.yml` запускается тем же `push`,
  находит recovery-прогон по `--trigger-sha`, ждёт `completed/success` и
  публикует комментарий `docs/testeb-64-comment.md` с маркером
  `<!-- testeb-64-recovery -->`.

## Доказательства

* Recovery workflow run:
  [#42469606322](https://github.com/sarnas-it/ebike-site/actions/runs/42469606322)
  — `completed`, `conclusion=success`, `event=push`, head
  `1c5d72de7533e42e88a70a0c637376966f3b7d8d`, ветка
  `feat/ebike-site-testeb-64`;
* проверяемый head PR #60:
  `b1b0871d6ebb4f7ce25a987c956c7b1431eef379`;
* зелёные проверки на нём: `php-lint=success`, `php-tests=success`,
  `plugin-tests=success`, commit status `ci/recover=success`;
* приёмочная команда:
  `php wp/plugins/velotok-core/tests/accounts-contract-test.php`;
* отложенный мерж PR #60 выполнен без ручного вмешательства
  `2026-11-08T10:01:27Z`, merge commit
  `4f8dce6768556a015b4f284c09d1c336c2cba292`.
