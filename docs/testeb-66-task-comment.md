# Запись комментария к задаче TESTEB-66

- Задача: TESTEB-66 — CI не дал результат (`no_checks`) по PR #71 (TESTEB-2)
- Записано: 2026-11-08
- Комментарий к PR #71 публикуется/обновляется workflow `Finalize PR #71 evidence` из `docs/testeb-66-comment.md`.

## Причина

Застрявший коммит `19c4f0dbfb0b14eb7b9ef5fc6ecd6207769e5008` не содержал
`.github/workflows` в своём дереве, а в базовой ветке `main` на момент открытия
PR workflow тоже не было. Для события `pull_request` GitHub ищет workflow в
дереве PR (head + base); ни в head, ни в base файла не было, поэтому запускать
было нечего — отсюда `no checks reported for the pull request`. GitHub не
запускает workflow ретроспективно, поэтому появление файла позже не создаёт
прогон для неизменённого старого head.

Фильтров по путям в CI нет. Очередь раннеров и лимиты минут не при чём: прогон
для этого SHA вообще не создавался. Allow-list интеграции содержит
`sarnas-it/ebike-site`.

## Что исправлено

- `.github/workflows/ci.yml` запускается на каждом `pull_request` без
  `paths`-фильтра, на `push` в `main` и по `workflow_dispatch`.
- Recovery-workflow `Recover PR #71 checks` берёт актуальный head PR #71 через
  GitHub API, проверяет его тем же `scripts/ci/run-checks.sh` и публикует
  `php-lint`, `php-tests`, `plugin-tests`, `php-plugin-tests`.
- `php-plugin-tests` исполняет обязательную команду
  `php wp/plugins/velotok-core/tests/accounts-contract-test.php`, а не только
  `php -l`.
- Финализатор находит recovery-прогон по `--trigger-sha` и больше не берёт
  `runId` из собственного `GITHUB_RUN_ID`; иначе он ждал бы сам себя и не
  публиковал комментарий.

## Доказательства

- Post-fix recovery-прогон:
  [#42488812111](https://github.com/sarnas-it/ebike-site/actions/runs/42488812111)
  — `completed/success`, триггер
  `1f59a83908f8535a0e6bf70f8b13b95c52d205ec`.
- Проверяемый коммит (актуальный head PR #71):
  `19c4f0dbfb0b14eb7b9ef5fc6ecd6207769e5008`.
- Проверки на коммите: `php-lint=success`, `php-tests=success`,
  `plugin-tests=success`, `php-plugin-tests=success`.
- `php wp/plugins/velotok-core/tests/accounts-contract-test.php`:
  `PASS: контракт аккаунтов выполняется.`, exit code 0.
- PR #71 merged=true, merge commit
  `99f296a30502af717ac31af5794928e5e540b136`.
- Предыдущий run
  [#42487589325](https://github.com/sarnas-it/ebike-site/actions/runs/42487589325)
  завершился `failure` только на шаге `workflow_dispatch` финализатора, которого
  ещё не было в базовой ветке; финальным доказательством он не считается.
