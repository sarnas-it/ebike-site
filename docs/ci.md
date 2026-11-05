# CI

## Причина no_checks по PR #5

У коммита `a6f85f6d4f8bb7b71a49d5a72316bc208d188fdf` в дереве не было
каталога `.github/workflows`: в нём есть `scripts/seed.php`, но нет ни одного
workflow. Поэтому событие `pull_request` для этого коммита не могло создать ни
одного прогона, а трекер видел `no checks reported for the pull request`.

Отключённые Actions, фильтры по путям, очередь раннеров, лимит минут и
allow-list интеграции не были причиной: запускать было нечего. После добавления
CI в PR #8 прогоны для `f0f5456f04ce5776a2d289754d82f6527f5067de` и
`13bd94476c526d704e8c992680b6d2bab74fc35e` завершились успешно.

Причина зафиксирована в комментарии к задаче TESTEB-209
(`7952fc2c-2ebd-4c4c-9bed-9bd133271ed3`):
<https://tracker.sarnas.ru/tasks/4ebc8798-0168-4ef3-a0ac-1de89d03f093>.
Текст комментария сохранён также в [testeb-209-comment.md](testeb-209-comment.md).

## Что исправлено

Добавлен workflow `.github/workflows/ci.yml`:

* запускается на каждом `pull_request` без фильтров по веткам и путям;
* запускается на любой `push` и вручную через `workflow_dispatch`;
* имеет таймаут 10 минут, чтобы отсутствие результата обнаруживалось раньше
  дедлайна отложенного мержа;
* использует только права на чтение содержимого репозитория.

Проверки выполняет `scripts/ci-check.sh`: синтаксис всех PHP-файлов, валидность
`content/products.json`, синтаксис shell-скриптов и контрактные тесты плагина,
если они присутствуют в проверяемом коммите.

## Восстановление проверок и отложенного мержа PR #5

GitHub не запускает новый workflow задним числом для коммита, в котором этого
workflow ещё не было. Поэтому `.github/workflows/recover-pr5.yml`:

1. выкачивает ровно коммит `a6f85f6d4f8bb7b71a49d5a72316bc208d188fdf`;
2. сверяет `git rev-parse HEAD` с целевым SHA (шаг `Record checked commit`);
3. запускает на этом дереве тот же `scripts/ci-check.sh` с PHP 8.2 и Node.js 20;
4. привязывает check run `Repository checks` к логу реального recovery-job;
5. публикует commit status для этого же SHA;
6. завершает уже одобренный отложенный мерж PR #5 через API.

Job идемпотентен: если PR #5 уже смержен, он не мержит повторно, но обновляет
ссылку на лог проверки. Если проверки на старом коммите не проходят, шаг
публикации не выполняется, а мерж не происходит.

### Подтверждённый результат

* Recovery workflow
  [run 41970869503](https://github.com/sarnas-it/ebike-site/actions/runs/41970869503)
  завершился успешно, job
  [127058551728](https://github.com/sarnas-it/ebike-site/actions/runs/41970869503/job/127058551728)
  — `success`, завершён в `2026-11-05T04:56:54Z`. Job выкачал ровно
  `a6f85f6d4f8bb7b71a49d5a72316bc208d188fdf` и выполнил на нём
  `scripts/ci-check.sh`.
* Check run `Repository checks` для
  `a6f85f6d4f8bb7b71a49d5a72316bc208d188fdf` — `success`
  ([check run 127058636746](https://github.com/sarnas-it/ebike-site/runs/127058636746)).
  Его `details_url` указывает на лог recovery-job, который выполнил проверки на
  этом SHA. Commit status для этого SHA также `success`.
* Отложенный мерж PR [#5](https://github.com/sarnas-it/ebike-site/pull/5)
  выполнен автоматически: `merged=true`,
  `merged_at=2026-11-05T04:56:49Z`,
  `merge_commit_sha=4be65ac54e231f6d06690b91f861ca1bdf860d11`.
* CI для коммита PR #8 `13bd94476c526d704e8c992680b6d2bab74fc35e` прошёл
  успешно: push-прогон
  [41970869484](https://github.com/sarnas-it/ebike-site/actions/runs/41970869484)
  и pull_request-прогон
  [41970874158](https://github.com/sarnas-it/ebike-site/actions/runs/41970874158).

Новый пуш этой сдачи запускает CI для нового коммита и recovery workflow заново.
Recovery повторно проверяет `a6f85f6` и обновляет `details_url` check run на лог
своего job, поэтому ссылка на подтверждение остаётся актуальной.

### Проверка состояния

```bash
bash scripts/verify-pr5.sh
```

Скрипт через GitHub API проверяет, что PR #5 смержен именно с head
`a6f85f6`, что для этого SHA есть успешный check run, привязанный к GitHub
Actions, и что есть успешный recovery-прогон. Для приватных репозиториев или
обхода rate limit можно передать `GH_TOKEN`.

## Локальный запуск

```bash
bash scripts/ci-check.sh
```

Для полного набора проверок нужны PHP 8.2+ и Node.js. Если PHP не установлен,
скрипт пропускает зависящие от него шаги и сообщает об этом.

## Настройки интеграции GitHub в трекере

Allow-list интеграции уже содержит `sarnas-it/ebike-site`: через интеграцию
созданы PR #5 и PR #8. Дополнительных изменений в настройках не требуется.
После появления workflow новые коммиты получают проверку автоматически, а для
старого коммита PR #5 проверка восстанавливается workflow `recover-pr5.yml`.
