# Запись комментария к задаче TESTEB-67

- Задача: TESTEB-67 — CI не дал результат (`no_checks`) по PR #73 (TESTEB-3)
- Записано: 2026-11-08
- Комментарий к PR #73 публикуется/обновляется workflow `Finalize PR #73 evidence` из `docs/testeb-67-comment.md`.

## Причина

Застрявший коммит `525b0d4803e03858cdb0bfce9cb90837bdf0473e` не содержал
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
- Recovery-workflow `Recover PR #73 checks` берёт актуальный head PR #73 через
  GitHub API, проверяет его тем же `scripts/ci/run-checks.sh` и публикует
  `php-lint`, `php-tests`, `plugin-tests`, `php-plugin-tests`.
- `php-plugin-tests` исполняет обязательную команду
  `php wp/plugins/velotok-core/tests/accounts-contract-test.php`, а не только
  `php -l`.
- Финализатор находит recovery-прогон по `--trigger-sha` и больше не берёт
  `runId` из собственного `GITHUB_RUN_ID`; иначе он ждал бы сам себя и не
  публиковал комментарий.

## Доказательства

- Проверяемый коммит (актуальный head PR #73):
  `525b0d4803e03858cdb0bfce9cb90837bdf0473e`.
- Recovery workflow run:
  [#42492889252](https://github.com/sarnas-it/ebike-site/actions/runs/42492889252)
  — `status=completed`, `conclusion=success`, `event=push`, workflow
  `.github/workflows/recover-pr73.yml`.
- Commit status на проверяемом коммите: `ci/recover=success` со ссылкой на
  run `42492889252`.
- Зелёные check runs на проверяемом коммите: `php-lint` (128768985954),
  `php-tests` (128768989481), `plugin-tests` (128768992188),
  `php-plugin-tests` (128768994590) — все `completed/success`.
- `php wp/plugins/velotok-core/tests/accounts-contract-test.php`:
  `PASS: контракт аккаунтов выполняется.`, exit code 0; команда входит в
  `php-plugin-tests`.
- PR #73 смержен без ручного вмешательства `2026-11-08T15:04:35Z`, merge
  commit `6a14f53349e6f7da967e1bd1aded48c12deb82be`.
- После нового push финализатор заменяет блок доказательств в
  `docs/testeb-67-comment.md` данными нового recovery-прогона через GitHub API.
