# activities

Angular-фронтенд + Symfony-бэкенд + MySQL, всё запускается в Docker.
Локально нужны только Docker и `make`.

## Быстрый старт

```bash
make build   # зависимости + образы + сборка фронта
make start
```

- Фронт: http://localhost:8080
- API: http://localhost:8080/api/ping

Сборка и запуск разделены: `make start` (`docker compose up`) ничего не собирает и не ставит, а только запускает уже подготовленное. Если зависимости не установлены, `make start` остановится и попросит выполнить `make install` (или `make build`).

## Make-таргеты

| Команда        | Что делает |
|----------------|---|
| `make build`   | Полная сборка: сначала `make install`, затем образы (`docker compose build`) и production-версия фронта (`yarn build`) в `./public` |
| `make install` | Ставит зависимости фронта (`yarn install`) и бэкенда (`composer install`) |
| `make start`   | Поднимает окружение в фоне из готовых образов (`docker compose up -d --no-build`) |
| `make stop`    | Останавливает и удаляет контейнеры (`docker compose down`), данные MySQL сохраняются |

## Как устроено

```
браузер → localhost:8080 → web (nginx) ─┬─ /api/* → backend (Symfony, php-fpm) → mysql
                                        └─ остальное → frontend (Angular dev-сервер)
```

| Сервис | Образ | Назначение |
|---|---|---|
| `web` | `nginx:alpine` | Единая точка входа: `/api/` → бэкенд, остальное → фронт |
| `frontend` | `node:22-alpine` | Angular dev-сервер с hot reload |
| `backend` | `php:8.5-fpm-alpine` + composer | Symfony (`./backend`), роуты начинаются с `/api` |
| `mysql` | `mysql:8.4` | База `activities`, пользователь/пароль `activities`/`activities` |

Все сервисы в сети `activities.loc`. Код фронта и бэкенда примонтирован в контейнеры — изменения подхватываются без перезапуска.

## Claude Code: Playwright MCP

В `.mcp.json` подключён общий для команды MCP-сервер `page-preview`
(Playwright) для просмотра страниц — через него Claude Code открывает
приложение в headless-браузере, кликает и делает скриншоты
(например, http://localhost:8080 после `make start`).

- Нужен Node.js (`npx`); пакет `@playwright/mcp` скачается при первом запуске.
- Если браузер не найден: `npx playwright install chromium`.
- При первом запуске `claude` в корне проекта спросит, доверять ли серверам
  из `.mcp.json`, — подтвердите. Проверить: `/mcp` или `claude mcp list`.
- В Docker/Linux-контейнере под root добавьте в `args` флаг `--no-sandbox`
  локально (через `claude mcp add ... -s local`), а не в общий файл.

## Полезные команды

```bash
docker compose logs -f frontend              # логи сервиса
docker compose exec backend bin/console      # консоль Symfony
docker compose exec backend composer require <пакет>
docker compose exec mysql mysql -uactivities -pactivities activities
```
