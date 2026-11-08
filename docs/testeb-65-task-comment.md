# Запись комментария к задаче TESTEB-65

- Задача: TESTEB-65 — CI не дал результат (`no_checks`) по PR #68 (TESTEB-29)
- Записано: 2026-11-08
- Комментарий к PR #68 публикуется/обновляется workflow
  `Finalize PR #68 evidence` из `docs/testeb-65-comment.md`.

## Причина

Застрявший коммит `766eade4e49a86d4c14791da8d33e735948cfadb` не содержал
`.github/workflows` в своём дереве, а в базовой ветке `main` на момент открытия
PR workflow тоже не было. Для события `pull_request` GitHub ищет workflow в
дереве PR (head + base); ни в head, ни в base файла не было, поэтому запускать
было нечего — отсюда `no checks reported for the pull request`. GitHub не
запускает workflow ретроспективно, поэтому появление файла позже не создаёт
прогон для неизменённого старого head.

Фильтров по путям в CI нет. Очередь раннеров и лимиты минут не при чём: прогон
для этого SHA вообще не создавался. Allow-list интеграции содержит
`sarnas-it/ebike-site`.

Отдельная вторая причина: первый recovery-прогон `42473326719` успешно
опубликовал проверки и смержил PR #68, но упал на шаге самодиспатча
финализатора. GitHub разрешает `workflow_dispatch` только для workflow, уже
существующего в базовой ветке, а `finalize-pr68-evidence.yml` на тот момент был
только в ветке починки. Из-за этого на head ветки починки оставался красный
check `Recover PR #68 checks`.

## Что исправлено

- `.github/workflows/ci.yml` запускается на каждом `pull_request` без
  `paths`-фильтра, на `push` в `main` и по `workflow_dispatch`.
- Recovery-workflow `Recover PR #68 checks` берёт актуальный head PR #68 через
  GitHub API, проверяет его тем же `scripts/ci/run-checks.sh` и публикует
  `php-lint`, `php-tests`, `php-plugin-tests`, `plugin-tests`.
- `php-plugin-tests` исполняет обязательную команду
  `php wp/plugins/velotok-core/tests/accounts-contract-test.php`, а также все
  остальные `wp/plugins/velotok-core/tests/*-test.php`, а не только `php -l`.
- Recovery больше не диспатчит финализатор. Финализатор запускается тем же
  `push`, находит recovery-прогон по `--trigger-sha` и не берёт `runId` из
  собственного `GITHUB_RUN_ID`; иначе он ждал бы сам себя и не публиковал
  комментарий.

## Доказательства

- Проверяемый коммит (актуальный head PR #68):
  `766eade4e49a86d4c14791da8d33e735948cfadb`.
- Первый recovery-прогон
  [#42473326719](https://github.com/sarnas-it/ebike-site/actions/runs/42473326719)
  опубликовал проверки на этом коммите и создал commit status
  `ci/recover=success` со ссылкой на run.
- PR #68 смержен без ручного вмешательства `2026-11-08T10:54:41Z`, merge
  commit `259f59dc6f4596219a050947341c17d107c77a68`.
- После этой починки финальный комментарий с `completed/success` публикует
  push-triggered `Finalize PR #68 evidence`, который заменяет блок
  доказательств в `docs/testeb-65-comment.md` данными нового
  recovery-прогона через GitHub API.
