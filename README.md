# Заодно

Каталог кружков и секций Владивостока с записью на занятия.
Angular-фронтенд + Symfony-бэкенд + MySQL, всё запускается в Docker.
Локально нужны только Docker и `make`.

## Быстрый старт

```bash
make build      # зависимости + образы + сборка фронта
make start
make jwt-keys   # dev-ключи JWT, без них не работает вход
make db-reset   # схема БД и демо-данные
```

- Фронт: http://localhost:8080
- API: http://localhost:8080/api/ping
- Демо-аккаунты — в разделе [«Демо-данные»](#демо-данные)

Сборка и запуск разделены: `make start` (`docker compose up`) ничего не собирает и не ставит, а только запускает уже подготовленное. Если зависимости не установлены, `make start` остановится и попросит выполнить `make install` (или `make build`).

## Демо-данные

`make db-reset` (и `app:seed` на проде, см. ниже) заливает каталог из 18 кружков и демо-аккаунты:

| Email | Пароль | Кто |
|---|---|---|
| `admin@example.com` | `admin` | администратор: модерация активностей |
| `maria@example.com` | `password` | Иванова Мария — основной демо-аккаунт, родитель и организатор |
| `anna.petrova@example.com` | `password` | Петрова Анна — заявка в «Акварель» за дочь Машу (7) |
| `igor.sidorov@example.com` | `password` | Сидоров Игорь — заявка в «Акварель» за себя |
| `vera.kozlova@example.com` | `password` | Козлова Вера — заявка в «Акварель» за сына Диму (9) |
| `elena.novikova@example.com` | `password` | Новикова Елена — заявка в «Акварель» за дочь Катю (6) |

У Марии:
- дети Артём (8) и Соня (5);
- записи: Артём — «Плавание · ДЮСШ №1», филиал «Борисенко» (подтверждена), Соня — «Восход» (на рассмотрении);
- активность «Акварель» — одобрена, по ней создан кружок `akvarel` с филиалами «Основной» и «Снеговая»;
  четыре заявки в него (от пользователей выше) ждут её подтверждения;
- активность «Настольные игры» — на модерации у админа.

На проде деплой выполняет `bin/console app:seed`: тот же набор накатывается, только если в базе ещё нет
пользователей (первая выкатка), дальше команда ничего не меняет. **Пароли выше публичны — после первой
выкатки смените их или удалите эти аккаунты.**

## Make-таргеты

| Команда        | Что делает |
|----------------|---|
| `make build`   | Полная сборка: сначала `make install`, затем образы (`docker compose build`) и production-версия фронта (`yarn build`) в `./public` |
| `make install` | Ставит зависимости фронта (`yarn install`) и бэкенда (`composer install`) |
| `make start`   | Поднимает окружение в фоне из готовых образов (`docker compose up -d --no-build`) |
| `make stop`    | Останавливает и удаляет контейнеры (`docker compose down`), данные MySQL сохраняются |

Бэкенд и проверки:

| Команда | Что делает |
|---|---|
| `make jwt-keys` | Генерирует dev-ключи JWT, если их ещё нет |
| `make console c="…"` | Команда `bin/console` в запущенном бэкенде |
| `make db-migration` | Генерирует миграцию по изменениям сущностей |
| `make db-migrate` | Применяет миграции к dev-базе |
| `make db-fixtures` | Перезаливает фикстуры (данные dev-базы удаляются) |
| `make db-reset` | Пересоздаёт dev-базу: удаление таблиц → миграции → фикстуры |
| `make test-db` | Создаёт и наполняет тестовую базу `activities_test` |
| `make lint` | `lint:container`, `lint:yaml`, проверка маппинга Doctrine, php-cs-fixer (без БД) |
| `make cs-fix` | Исправляет стиль кода бэкенда |
| `make typecheck` | Проверка типов фронтенда |
| `make test` | Тесты бэкенда (PHPUnit) |
| `make check` | `lint` + `typecheck` + `test` |

Таргеты `console`, `db-*`, `test-db` и `test` работают в запущенном окружении (`make start`); перед `make test` нужен `make test-db`.

## Как устроено

```
браузер → localhost:8080 → web (nginx) ─┬─ /api/* → backend (Symfony, php-fpm) → mysql
                                        ├─ /personal/, /org/ → статика из ./public (устаревшие приложения)
                                        └─ остальное → frontend (Angular dev-сервер)
```

| Сервис | Образ | Назначение |
|---|---|---|
| `web` | `nginx:alpine` | Единая точка входа: `/api/` → бэкенд, остальное → фронт |
| `frontend` | `node:22-alpine` | Angular dev-сервер с hot reload |
| `backend` | `php:8.5-fpm-alpine` + `intl`, composer | Symfony (`./backend`), роуты начинаются с `/api` |
| `mysql` | `mysql:8.4` | База `activities`, пользователь/пароль `activities`/`activities` |
| `page-preview` | `mcr.microsoft.com/playwright/mcp` | MCP-сервер для Claude Code (браузер), `127.0.0.1:8931` — только локально |

Все сервисы в сети `activities.loc`. Код фронта и бэкенда примонтирован в контейнеры — изменения подхватываются без перезапуска.

## Claude Code: Playwright MCP

MCP-сервер `page-preview` (Playwright) для просмотра страниц: через него
Claude Code открывает приложение в headless-браузере, кликает и делает
скриншоты. Работает только при локальной разработке — поднимается сервисом
`page-preview` в `docker-compose.yml` вместе с `make start` и слушает
`http://localhost:8931/mcp` (адрес прописан в `.mcp.json`). Node.js и браузер
на машине ставить не нужно.

- Сайт открывается по **http://127.0.0.1/** (без `:8080`): контейнер MCP
  делит сеть с `web`, а nginx внутри слушает 80. Например: «открой
  http://127.0.0.1/ через page-preview и сделай скриншот».
- Сервер заранее разрешён в `.claude/settings.json` (`enabledMcpjsonServers`),
  поэтому подтверждать его при запуске `claude` не нужно — он подключается
  сам, если окружение поднято через `make start`. Проверить: `/mcp` или
  `claude mcp list`. MCP-серверы подключаются только при старте `claude`,
  так что после `make start` перезапустите уже открытую сессию.
- Из-за общей сети после перезапуска одного `web` MCP отваливается —
  используйте `make start` / `docker compose up -d`, они пересоздают оба.
- У сервера нет авторизации, поэтому порт открыт только на `127.0.0.1`,
  а в `docker-compose.prod.yml` сервиса нет и не должно быть.

## Полезные команды

```bash
docker compose logs -f frontend              # логи сервиса
docker compose exec backend bin/console      # консоль Symfony
docker compose exec backend composer require <пакет>
docker compose exec mysql mysql -uactivities -pactivities activities
```
