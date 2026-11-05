# CI

## Причина no_checks по PR #7

Коммит `92b31d9ed3ccc55a6195a45f91db56cbc3202951` (head PR #7) не содержит
каталога `.github/workflows` вообще. На момент инцидента GitHub API для этого
SHA возвращал:

* `GET /repos/sarnas-it/ebike-site/commits/92b31d9.../check-runs` —
  `total_count: 0`;
* `GET /repos/sarnas-it/ebike-site/commits/92b31d9.../status` —
  `total_count: 0`, состояние `pending`.

Запускать было нечего: workflow в дереве коммита отсутствует, поэтому событие
`pull_request` не могло создать ни одного прогона. Это и есть причина
`no checks reported for the pull request`.

Остальные проверяемые причины исключены:

* GitHub Actions в репозитории не отключены — в это же время PR #8 получил
  успешные прогоны `.github/workflows/ci.yml` (run `41971188668` и др.);
* фильтров по путям и веткам в CI нет, триггеры `push` и `pull_request`
  широкие;
* очередь раннеров и лимит минут не при чём: job PR #8 завершился за секунды
  на `ubuntu-latest`;
* allow-list интеграции содержит `sarnas-it/ebike-site`: PR #7 создан через
  интеграцию, и её токен открывает PR в этом репозитории.

Дополнительно PR #7 стал `mergeable_state: dirty`: после его создания в `main`
влился PR #5, и оба PR добавили `docker-compose.yml`. GitHub не может выполнить
отложенный мерж до разрешения конфликта.

## Фактическое подтверждение

Проверки на целевом SHA уже действительно выполнялись, а не только описаны в
документации:

* recovery-прогон
  [41974163032](https://github.com/sarnas-it/ebike-site/actions/runs/41974163032)
  выкачал ровно `92b31d9...`, шаг `Record checked commit` сверил
  `git rev-parse HEAD`, и на этом дереве прошёл `scripts/ci-check.sh`;
* для `92b31d9...` создан check run `Repository checks` —
  `conclusion: success` (id `127069146854`,
  <https://github.com/sarnas-it/ebike-site/runs/127069146854>); в его summary
  указан лог recovery-прогона;
* commit status `Repository checks` для этого SHA — `success`
  (<https://github.com/sarnas-it/ebike-site/actions/runs/41974163032>);
* тот же прогон влил актуальный `main` в ветку PR #7 и создал merge-коммит
  `d97063d7ceed44be26e7544364d62f4478074460`, но завершился с ошибкой до
  публикации проверки для нового head и до мержа PR.

### Почему предыдущий recovery-прогон упал

В `sync_branch_with_main` результат команды `new_sha="$(sync_branch_with_main ...)"`
содержал не только SHA, но и вывод `git merge`, `git commit` и
`scripts/ci-check.sh`. Из-за этого в API уходил многострочный `head_sha`,
создание check run завершалось ошибкой, job падал на шаге публикации, а мерж PR
#7 не выполнялся. Теперь весь вывод этих команд направлен в stderr, и функция
возвращает ровно один SHA. Дополнительно имена workflow и job взяты в кавычки:
без кавычек YAML считал `#7` комментарием, поэтому job назывался `Recover PR`,
и скрипт не находил ссылку на реальный job.

## Что исправлено

* `.github/workflows/ci.yml` запускается на каждом `push`, каждом
  `pull_request` без фильтров и вручную через `workflow_dispatch`.
* Проверки вынесены в `scripts/ci-check.sh`: синтаксис всех PHP-файлов,
  валидность JSON-файлов, синтаксис shell-скриптов и контрактные тесты плагина,
  если они есть в проверяемом коммите. Скрипт принимает необязательный корень
  checkout, поэтому его можно выполнить на старом коммите, не загрязняя его
  дерево.
* `.github/workflows/recover-pr7.yml` восстанавливает проверки для старого
  коммита и завершает отложенный мерж.
* `scripts/recover-pr7.sh` проверяет не только целевой `92b31d9...`, но и
  текущий head PR #7, если ветка уже была синхронизирована с `main`, поэтому
  успешный check run появляется ровно на том коммите, который мержит GitHub.

## Восстановление проверок и отложенного мержа PR #7

GitHub не запускает новый workflow задним числом для коммита, в котором этого
workflow ещё не было. Поэтому `recover-pr7.yml`:

1. выкачивает ровно коммит `92b31d9ed3ccc55a6195a45f91db56cbc3202951`;
2. шагом `Record checked commit` сверяет `git rev-parse HEAD` с целевым SHA;
3. запускает на этом дереве `scripts/ci-check.sh` с PHP 8.2 и Node.js 20;
4. создаёт check run `Repository checks` для этого SHA со ссылкой на лог
   реального recovery-job и публикует commit status;
5. если текущий head PR #7 уже отличается от `92b31d9...` (ветка была
   синхронизирована предыдущим запуском), проверяет и публикует такой же
   успешный check run для этого head;
6. если PR #7 конфликтует с текущим `main`, вливает `main` в ветку PR,
   разрешает единственный конфликт `docker-compose.yml` в пользу уже рабочей
   версии из `main` и сохраняет остальные файлы PR #7 (`wp/cli`, `wp/tests`,
   `wp/docker`, `wp/data`);
7. повторно прогоняет `scripts/ci-check.sh` на объединённом дереве, публикует
   check run для нового head и завершает отложенный мерж PR #7 через API.

Job идемпотентен: если PR уже смержен, он не мержит повторно, но обновляет
ссылку на лог проверки. Если проверки на старом коммите или на текущем head не
проходят либо конфликтует что-то кроме `docker-compose.yml`, публикация успеха
и мерж не выполняются.

### Проверка состояния

```bash
bash scripts/verify-pr7.sh
```

Скрипт через GitHub API проверяет, что PR #7 смержен, что для
`92b31d9ed3ccc55a6195a45f91db56cbc3202951` есть успешный check run
`Repository checks`, привязанный к GitHub Actions, и что есть успешный
recovery-прогон. Для приватных репозиториев или обхода rate limit можно
передать `GH_TOKEN`.

### Комментарий к задаче

Текст root-cause комментария для TESTEB-211 сохранён в
[testeb-211-comment.md](testeb-211-comment.md): он содержит ссылки на recovery
run и успешный check run целевого SHA. Merge commit и итоговое состояние PR
записываются шагом `Record final PR and check state` в summary
recovery-прогона.

## Локальный запуск

```bash
bash scripts/ci-check.sh
```

Для полного набора нужны PHP 8.2+ и Node.js. Если PHP не установлен, скрипт
пропускает зависящие от него шаги и сообщает об этом.
