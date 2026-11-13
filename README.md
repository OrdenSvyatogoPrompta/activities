# activities

Angular-фронтенд + Symfony-бэкенд + MySQL, всё запускается в Docker.
Локально нужны только Docker и `make`.

## Быстрый старт

```bash
make start
```

- Фронт: http://localhost:8080
- API: http://localhost:8080/api/ping

Первый запуск занимает несколько минут: контейнеры ставят зависимости (`yarn install`, `composer install`) и компилируют фронт.

## Make-таргеты

| Команда | Что делает |
|---|---|
| `make start` | Собирает образы и поднимает всё окружение в фоне (`docker compose up -d --build`) |
| `make stop` | Останавливает и удаляет контейнеры (`docker compose down`), данные MySQL сохраняются |
| `make install` | Ставит зависимости фронта (`yarn install`) в контейнере `node:22-alpine` |
| `make build` | Собирает production-версию фронта (`yarn build`) в `./public` |

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

## Полезные команды

```bash
docker compose logs -f frontend              # логи сервиса
docker compose exec backend bin/console      # консоль Symfony
docker compose exec backend composer require <пакет>
docker compose exec mysql mysql -uactivities -pactivities activities
```
