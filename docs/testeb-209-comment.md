# Комментарий к задаче TESTEB-209

Опубликован в трекере 2026-11-05, id `7952fc2c-2ebd-4c4c-9bed-9bd133271ed3`:
<https://tracker.sarnas.ru/tasks/4ebc8798-0168-4ef3-a0ac-1de89d03f093>.

## Причина no_checks

У коммита `a6f85f6d4f8bb7b71a49d5a72316bc208d188fdf` в дереве не было каталога
`.github/workflows`: в нём есть `scripts/seed.php`, но нет ни одного workflow.
Поэтому событие `pull_request` для этого коммита не могло создать ни одного
прогона, и трекер увидел `no checks reported for the pull request`.

Отключённые Actions, фильтры по путям, очередь раннеров, лимит минут и allow-list
интеграции не были причиной: запускать было нечего. После добавления CI в PR #8
прогоны для `f0f5456f04ce5776a2d289754d82f6527f5067de` и
`13bd94476c526d704e8c992680b6d2bab74fc35e` завершились успешно.

## Проверки по коммиту `a6f85f6`

Recovery workflow
[run 41970869503](https://github.com/sarnas-it/ebike-site/actions/runs/41970869503)
завершился успешно; его job
[127058551728](https://github.com/sarnas-it/ebike-site/actions/runs/41970869503/job/127058551728)
выкачал ровно `a6f85f6d4f8bb7b71a49d5a72316bc208d188fdf` (шаг
`Record checked commit` сверяет `git rev-parse HEAD`) и выполнил на нём
`scripts/ci-check.sh` с PHP 8.2 и Node.js 20. Check run `Repository checks` на
этот SHA — `success`, его `details_url` привязан к логу recovery-job; commit
status также `success`.

## Отложенный мерж PR #5

PR [#5](https://github.com/sarnas-it/ebike-site/pull/5) имеет `merged=true`,
`merged_at=2026-11-05T04:56:49Z`,
`merge_commit_sha=4be65ac54e231f6d06690b91f861ca1bdf860d11`; мерж выполнен
recovery-запуском без ручного вмешательства.

## Состояние проверок PR #8

Для `13bd944` успешно прошли push-прогон
[41970869484](https://github.com/sarnas-it/ebike-site/actions/runs/41970869484)
и pull_request-прогон
[41970874158](https://github.com/sarnas-it/ebike-site/actions/runs/41970874158).
Новый пуш этой сдачи запустит CI и recovery workflow заново: CI проверит новый
коммит, а recovery обновит `details_url` check run для `a6f85f6` на лог своего
job.

## PR с починкой CI

[#8](https://github.com/sarnas-it/ebike-site/pull/8). Подробности и ссылки
продублированы в [docs/ci.md](ci.md).
