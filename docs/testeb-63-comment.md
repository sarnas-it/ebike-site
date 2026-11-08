# Комментарий к задаче TESTEB-63

<!-- testeb-63-recovery -->

Причина `no_checks` по PR [#62](https://github.com/sarnas-it/ebike-site/pull/62):
застрявший коммит `578e87b5a8587d6078aade92d26bd449448dd1d3` не содержит
`.github/workflows` в своём дереве, а базовая ветка `main` на момент открытия и
последнего push PR была пустой (`40134b5`, `chore: empty repository`) и тоже не
содержала workflow. Для `pull_request` GitHub ищет workflow-файл в дереве PR;
запускать было нечего, поэтому не появилось ни одного прогона — отсюда
`no checks reported for the pull request`. GitHub не запускает workflow
ретроспективно, поэтому появление файла в другой ветке или позже в `main` само
по себе не создаёт прогон для неизменённого старого head.

Это не противоречит тому, что workflow `ci` активен и соседние PR зелёные:
ветки соседних задач (`feat/ebike-site-testeb-15`, `...-44`, `...-50`) сами
добавляют `.github/workflows/ci.yml` в своё дерево, поэтому в их PR workflow
есть. Ветка PR #62 такой файл не добавляла, а `main` тогда был пустым. Actions
включены, фильтров по путям нет, очередь раннеров и лимиты минут не при чём,
allow-list интеграции содержит `sarnas-it/ebike-site`.

Исправление: PR [#65](https://github.com/sarnas-it/ebike-site/pull/65) добавляет
`.github/workflows/ci.yml` в базовую ветку, поэтому новые PR получают реальные
прогоны на каждом `pull_request`, `push` в `main` и по `workflow_dispatch`.
Recovery-workflow `Recover PR #62 checks`
(`.github/workflows/recover-pr62.yml`) выкачивает ровно коммит
`578e87b5a8587d6078aade92d26bd449448dd1d3` (`git rev-parse HEAD` сверяется с
целевым SHA), прогоняет на нём те же проверки, что и обычный CI
(`php-lint`, `php-tests`, `plugin-tests`), публикует результаты на этот SHA и
завершает отложенный мерж PR #62. Мерж выполняется только если все проверки
зелёные и head PR всё ещё равен проверенному коммиту.

Ниже автоматически добавлено подтверждение из реального recovery-прогона:
ссылка на run, результаты проверок на целевом коммите и merge commit. Если
recovery-прогон не завершился успешно или проверки не зелёные, скрипт
`scripts/ci/publish-evidence.mjs` не публикует подтверждение, а завершает job
ошибкой.
