# CLAUDE.md

## О проекте

**«Заодно»** — каталог детских (и не только) кружков и секций Владивостока с записью на занятия.
Пользователь ищет кружок по фильтрам, смотрит карточку с филиалами и расписанием, записывает себя
или ребёнка. Один аккаунт одновременно может быть и «родителем» (записи, дети), и «организатором»
(свои активности, входящие записи) — отдельного кабинета организации нет.

Статус: **прототип**. Фронтенд работает через JSON API бэкенда (JWT), данные — в MySQL.
Контракт API, доменная модель и открытые вопросы — `docs/backend-requirements.md`.

Продуктовые требования — `Требования к MVP-прототипу «Место встречи» (v2).md` в корне.
Документ сейчас дорабатывается владельцем, не опираться на него без явной просьбы.

## Стек

| Слой | Технологии |
|---|---|
| Фронтенд | Angular 21 (standalone-компоненты, signals, новый control flow `@if/@for`, OnPush), Nx 22, TypeScript 5.9, SCSS, Yarn 4 (corepack), Node 22 |
| Бэкенд | PHP 8.5 (php-fpm), Symfony 8.1: Doctrine ORM + Migrations, Serializer, Validator, Security, Workflow, LexikJWT + gesdinet refresh-token 3.x; PHPUnit, php-cs-fixer |
| БД | MySQL 8.4 |
| Инфраструктура | Docker Compose, nginx, Caddy (prod), GitHub Actions → GHCR, Dependabot |

## Структура

```
activities/
├── frontend/                 # Nx-воркспейс Angular
│   └── apps/
│       ├── main/             # ОСНОВНОЕ приложение (единственное собирается `yarn build`)
│       ├── personal/         # устаревшая заглушка ЛК (бренд «Activities»), base href /personal/
│       └── org/              # устаревшая заглушка кабинета организации, base href /org/
├── backend/                  # Symfony; код в src/, роуты атрибутами, все под /api
├── docker/
│   ├── default.conf          # nginx для dev
│   ├── php/Dockerfile        # dev-образ php-fpm (+intl) + composer
│   └── prod/                 # Dockerfile'ы web/backend, nginx.conf, Caddyfile
├── docker-compose.yml        # dev-окружение
├── docker-compose.prod.yml   # prod (образы из GHCR, hardening)
├── .github/                  # workflow деплоя, dependabot
├── docs/backend-requirements.md  # контракт API, доменная модель, ход работ
└── .claude/skills/           # правила проекта: commit, branch, code-style
```

## Запуск и команды

Локально нужны только Docker и `make`. Всё выполняется в контейнерах.

```bash
make build     # yarn install + composer install + docker compose build + yarn build (в ./public)
make start     # docker compose up -d --no-build (ничего не собирает)
make jwt-keys  # dev-ключи JWT (первый запуск)
make db-reset  # схема + фикстуры в dev-базе
make stop      # docker compose down (данные MySQL сохраняются)
make install   # только зависимости
```

- Фронт: http://localhost:8080, API: http://localhost:8080/api/ping
- Демо-аккаунты из фикстур: `maria@example.com` / `password`, админ `admin@example.com` / `admin`
- Остальные таргеты (`db-*`, `test-db`, `test`, `lint`, `cs-fix`, `typecheck`, `check`, `console`) — в `README.md`.
- Composer: `docker compose exec backend composer require <пакет>`
- MySQL: `docker compose exec mysql mysql -uactivities -pactivities activities`
- Фронт-скрипты (`frontend/package.json`): `start` (= `nx serve main`), `build`, `typecheck`.
  Запускать через `docker compose run --rm --no-deps frontend corepack yarn <script>`.

## Как устроен трафик

Dev:
```
браузер → localhost:8080 → web (nginx) ─┬─ /api/*      → backend (php-fpm) → mysql
                                        ├─ /personal/, /org/ → статика из ./public (устаревшие приложения)
                                        └─ остальное   → frontend (ng serve, hot reload)
```
Код примонтирован в контейнеры, изменения подхватываются без перезапуска.

Prod: `caddy (TLS, самоподписанный) → web (nginx-unprivileged, статика main + /api → backend) → backend → mysql`.
Контейнеры read-only, `cap_drop: ALL`; сеть `internal` **без выхода в интернет** — бэкенд не сможет
ходить во внешние API, пока это не поменять. Деплой (`.github/workflows/deploy.yml`): каждый push
в `main` (в т.ч. мерж PR) сразу собирает образы в GHCR, затем задача `deploy` того же запуска ждёт
ручного подтверждения (кнопка «Review deployments»; ожидание задаётся правилом Required reviewers
окружения `production`). Повторная выкатка без пересборки — Re-run задачи `deploy`. Деплой применяет миграции
(`doctrine:migrations:migrate`) и берёт ключи JWT из секретов окружения `production`
(`JWT_SECRET_KEY`, `JWT_PUBLIC_KEY` — содержимое PEM, `JWT_PASSPHRASE`). Админа на проде выдаёт
`bin/console app:user:promote <email> --admin`.

## Фронтенд (`frontend/apps/main`)

Префикс селекторов `act-`. Алиас импорта `~/*` → `src/app/*`. API-база `environment.apiUrl = '/api'`.
`strictNullChecks: false`, `strictTemplates: true`.

### Маршруты (`app.routes.ts`, все lazy)

| Путь | Компонент | Что делает |
|---|---|---|
| `/` | `home/HomeComponent` | Каталог: поиск, чипы категорий, панель фильтров (цена/Пушкинская карта, возраст, дни недели, формат), сетка карточек. Фильтрация на клиенте по полному списку |
| `/club/:id` | `club/ClubComponent` | Карточка кружка (`id` = slug): описание, филиалы, контакты; окно записи (филиал, «для себя»/ребёнок) → заявка `pending` |
| `/auth/login`, `/auth/register` | `auth/*` | Вход и регистрация через API, после — `returnUrl` или `/personal` |
| `/personal` | `personal/PersonalDashboardComponent` (`authGuard`) | ЛК, 3 вкладки: **Мои записи** (список с фильтром по участнику + календарь с листанием месяцев), **Активности** (свои, статус модерации, входящие заявки по филиалам, форма создания), **Дети** (добавление, правка, удаление) |
| `/admin` | `admin/AdminDashboardComponent` (`adminGuard`) | Модерация: одобрение/отклонение активностей, заявки в кружки без владельца, снятие с публикации |
| `/org` | — | редирект на `/personal` |

`org/OrgDashboardComponent` в `main` не подключён к роутам (остаток).

### HTTP-слой и сервисы

- `api/api-client.ts` — обёртка `HttpClient` (`withCredentials` для refresh-cookie); `api/auth.interceptor.ts` —
  Bearer, на `401` один `POST /auth/refresh` и повтор, при неудаче выход; `api/api-error.ts` — разбор problem+json
  и `violations` в ошибки полей.
- `AuthService` — access-токен в памяти, тихий refresh при старте, `user`, `isAdmin`.
- `ClubsService`, `EnrollmentsService`, `ChildrenService`, `ActivitiesService`, `AdminService`; списки грузятся
  через общий `services/remote-list.ts` (`RemoteList<T>`) и сбрасываются при выходе.
- Модели — `models/*.model.ts`, совпадают с JSON API (перечисления на русском, как в API).
- `utils/format.ts` (склонение «лет», даты), `utils/illustrations.ts` (SVG-обложки по категории и цвету).

### Стили

Глобальные токены в `src/styles/styles.scss` (`--brand-orange #f25e02`, `--brand-purple #6880f8`, нейтральные цвета).
Стили компонентов в соседних `.scss`, BEM-подобные классы. Мобильная адаптивность есть.

## Бэкенд (`backend`)

- Обязательно прочитать `backend/AGENTS.md` — соглашения: компоненты через `composer require`
  (Flex-рецепты, не править `bundles.php` руками), роуты атрибутами, тонкие контроллеры,
  `#[MapRequestPayload]`/`#[MapQueryString]`, readonly DTO, схема БД только через миграции, стиль `@Symfony`.
- Все роуты под `/api` (так настроен nginx). Код разложен по ресурсам: `Controller|Dto|Service/<Ресурс>`
  (`Auth`, `Catalog`, `Children`, `Enrollment`, `Activity`, `Admin`).
- Сущности: `User`, `Child`, `Club` (каталог, `id` в API = slug), `Branch`, `ScheduleSlot`, `Activity`
  (заявка организатора, после одобрения админом становится `Club`), `ActivityBranch`, `Enrollment`, `RefreshToken`.
  UUID v7. Схема — одна миграция в `migrations/`.
- Заявка на запись — state machine `enrollment` (`pending → confirmed|rejected|cancelled`, `confirmed → cancelled`);
  права — `Security/Voter/EnrollmentVoter` (подтверждает владелец кружка или `ROLE_ADMIN`, отменяет автор).
- Auth: JWT access 15 мин в `Authorization: Bearer`, refresh 30 дней в HttpOnly cookie (`Path=/api/auth`, single-use).
  Публичны `/api/auth/{register,login,refresh}`, `GET /api/clubs*`, `/api/dictionaries`, `/api/ping`; `/api/admin/*` — `ROLE_ADMIN`.
- Ошибки — `application/problem+json` (`src/Http/Problem`), тексты на русском; `#[Assert]` — с русским `message:`
  (`symfony/translation` не установлен).
- Поля для показа (`price`, `age`, `schedule`) формирует сервер (`Service/Formatter`).
- Фикстуры (`DataFixtures`, dev/test): 18 сидовых кружков без владельца + кружок `akvarel` демо-пользователя,
  демо-пользователь с детьми и заявками, заявители, админ.

## Тесты и линт

- Бэкенд: `tests/Unit` (без БД) и `tests/Functional` (`WebTestCase`, общий `ApiTestCase`, нужна `make test-db`;
  DAMA откатывает транзакции). Статика — `make lint`.
- Фронт: тестов нет; ESLint не настроен (`@nx/eslint` подключён, конфигурации нет). Проверка — `yarn typecheck`
  и `yarn build` (бюджет стилей компонента — 16 kB).

## Правила работы в репозитории

- Код: скилл `.claude/skills/code-style` — лаконичные комментарии, без одноразового и дублирующегося кода.
- Git: см. скиллы `.claude/skills/commit` и `.claude/skills/branch`.
  Кратко: не коммитить в `main`, ветки `<type>/<kebab-case>`, Conventional Commits,
  **сообщения на русском в прошедшем времени от первого лица** («добавил…»),
  слияние только `--no-ff`, **без Co-Authored-By Claude и без «Generated with Claude Code»**
  в коммитах и PR. Коммитить/пушить/мержить только по просьбе пользователя.
- Не коммитить `.env.local`, `.idea/`, `public/`, `node_modules/`, `vendor/`, `var/`.
- Язык общения, документации и UI — русский.

## Известный технический долг

- `apps/personal` и `apps/org` — устаревшие заглушки, не собираются `yarn build`, но под них есть
  location'ы в dev-nginx (`/personal/`, `/org/`). Актуальный ЛК — маршрут `/personal` в `main`.
- `quickAge`/`selectQuickAge` в `HomeComponent` есть в логике, но не выведены в UI.
- Функциональные тесты бэкенда в CI не гоняются.
- Дубль активной заявки защищён только в приложении (блокировка строки пользователя), не индексом БД.
- Удаление ребёнка каскадно удаляет историю его заявок (`ON DELETE CASCADE`).
- Остальные открытые вопросы — раздел «Открытые вопросы» в `docs/backend-requirements.md`.
