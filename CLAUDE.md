# CLAUDE.md

## О проекте

**«Заодно»** — каталог детских (и не только) кружков и секций Владивостока с записью на занятия.
Пользователь ищет кружок по фильтрам, смотрит карточку с филиалами и расписанием, записывает себя
или ребёнка. Один аккаунт одновременно может быть и «родителем» (записи, дети), и «организатором»
(свои активности, входящие записи) — отдельного кабинета организации нет.

Статус: **прототип**. Фронтенд реализован на моках (данные захардкожены в сервисах, состояние живёт
в памяти и теряется при перезагрузке). Бэкенд — пустой скелет Symfony с одним эндпоинтом `/api/ping`.

Продуктовые требования — `Требования к MVP-прототипу «Место встречи» (v2).md` в корне.
Документ сейчас дорабатывается владельцем, не опираться на него без явной просьбы.

## Стек

| Слой | Технологии |
|---|---|
| Фронтенд | Angular 21 (standalone-компоненты, signals, новый control flow `@if/@for`, OnPush), Nx 22, TypeScript 5.9, SCSS, Yarn 4 (corepack), Node 22 |
| Бэкенд | PHP 8.5 (php-fpm), Symfony 8.1 (только FrameworkBundle, Console, Dotenv, Yaml, Runtime) |
| БД | MySQL 8.4 (поднята, но бэкендом пока не используется) |
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
│   ├── php/Dockerfile        # dev-образ php-fpm + composer
│   └── prod/                 # Dockerfile'ы web/backend, nginx.conf, Caddyfile
├── docker-compose.yml        # dev-окружение
├── docker-compose.prod.yml   # prod (образы из GHCR, hardening)
├── .github/                  # workflow деплоя, dependabot
├── docs/deploy-checklist.md  # чеклист прод-деплоя
└── .claude/skills/           # правила проекта: commit, branch
```

## Запуск и команды

Локально нужны только Docker и `make`. Всё выполняется в контейнерах.

```bash
make build     # yarn install + composer install + docker compose build + yarn build (в ./public)
make start     # docker compose up -d --no-build (ничего не собирает)
make stop      # docker compose down (данные MySQL сохраняются)
make install   # только зависимости
```

- Фронт: http://localhost:8080, API: http://localhost:8080/api/ping
- Консоль Symfony: `docker compose exec backend bin/console ...`
- Composer: `docker compose exec backend composer require <пакет>`
- MySQL: `docker compose exec mysql mysql -uactivities -pactivities activities`
- Фронт-скрипты (`frontend/package.json`): `start` (= `nx serve main`), `build`, `typecheck`, `lint`.
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
окружения `production`). Повторная выкатка без пересборки — Re-run задачи `deploy`. Миграций в деплое пока нет.

## Фронтенд (`frontend/apps/main`)

Префикс селекторов `act-`. Алиас импорта `~/*` → `src/app/*`. API-база: `environment.apiUrl = '/api'`
(пока нигде не используется, `HttpClient` подключён, но запросов нет). `strictNullChecks: false`,
`strictTemplates: true`.

### Маршруты (`app.routes.ts`, все lazy)

| Путь | Компонент | Что делает |
|---|---|---|
| `/` | `home/HomeComponent` | Каталог: поиск, чипы категорий, панель фильтров (цена/Пушкинская карта, возраст, дни недели, формат), сетка карточек. Фильтрация на клиенте |
| `/club/:id` | `club/ClubComponent` | Карточка кружка: описание, филиалы (табы), контакты, теги; bottom sheet записи (филиал, «для себя»/ребёнок), модалка успеха |
| `/auth/login`, `/auth/register` | `auth/*` | Формы; оба вызывают мок `AuthService.login()` и ведут в `/personal` |
| `/personal` | `personal/PersonalDashboardComponent` | Единый ЛК, 3 вкладки: **Мои записи** (список с фильтром по ребёнку + календарь месяца), **Активности** (свои кружки, детализация по филиалам с входящими записями, форма создания), **Дети** (список, добавление/удаление) |
| `/org` | — | редирект на `/personal` |

`org/OrgDashboardComponent` в `main` не подключён к роутам (остаток).

### Данные и сервисы (моки)

- `services/clubs.service.ts` — `ClubsService.getAll()/getById()` над массивом `CLUBS` (~18 кружков).
- `services/auth.service.ts` — `AuthService` со signal `user`; `MOCK_USER` с детьми, записями и одной
  активностью. Методы: `login` (любой email/пароль, имя = часть email до `@`), `logout`, `addChild`,
  `removeChild`, `enroll`, `cancelEnrollment`, `addActivity`. Всё синхронно, в памяти.
- `models/club.model.ts` — `Club`, `Branch`, `ClubCategory` (`Спорт|Творчество|Наука|Музыка|Танцы`),
  `PriceType` (`free|pushkin|paid`), `VisitType` (`recurring|oneTime|both`), `DayOfWeek` (`Пн`…`Вс`).
- В `auth.service.ts` — `AuthUser`, `Child`, `Enrollment`, `ManagedActivity`, `ActivityBranch`, `IncomingEnrollment`.
- `utils/illustrations.ts` — генерация SVG-обложек по категории и цвету.

### Особенности моделей, важные при подключении API

- **Каталог (`Club`) и «свои активности» (`ManagedActivity`) — две несвязанные модели.**
  Созданная в ЛК активность не появляется в каталоге; поля различаются (`categories[]` vs `category`,
  `schedule`-строка vs `days + timeFrom/timeTo`).
- Расписание дублируется: человекочитаемая строка `schedule` + массив `days` для фильтров/календаря.
- `Enrollment` денормализован (хранит `clubName`, `branchName`, `schedule`, `color`, копию `Child`).
- `IncomingEnrollment` привязан к филиалу по **имени** (`branchName`), не по id.
- Цена: строка для отображения `price` + число `priceAmount` + `priceType`.
- Id — строки (у кружков — slug, например `swimming-dyush`).
- Регистрация игнорирует поле «Имя»; защиты маршрутов (guards) нет — ЛК сам проверяет `user()`.
- Календарь строится только на текущий месяц, по дням недели из `Enrollment.days`.

### Стили

Глобальные токены в `src/styles/styles.scss` (`--brand-orange #f25e02`, `--brand-purple #6880f8`, нейтральные цвета).
Стили компонентов в соседних `.scss`, BEM-подобные классы. Мобильная адаптивность есть.

## Бэкенд (`backend`)

- Только `src/Kernel.php` и `src/Controller/PingController.php` (`GET /api/ping`).
- Не установлены: Doctrine ORM/Migrations, Serializer, Validator, Security. `DATABASE_URL` задан в
  окружении, но не используется.
- Обязательно прочитать `backend/AGENTS.md` — соглашения: ставить компоненты через
  `composer require` (Flex-рецепты, не править `bundles.php` руками), роуты атрибутами,
  тонкие контроллеры, `#[MapRequestPayload]`/`#[MapQueryString]`, readonly DTO, схема БД только через
  миграции, функциональные тесты на `WebTestCase`, стиль `@Symfony`.
- Все роуты должны начинаться с `/api` (так настроен nginx).

## Тесты и линт

Тестов нет ни на фронте, ни на бэке. В `nx.json` подключён плагин `@nx/eslint`, но конфигурации
ESLint в репозитории нет — `yarn lint` фактически не настроен. Рабочая проверка фронта — `yarn typecheck`
и `yarn build`.

## Правила работы в репозитории

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
- Нет HTTP-слоя: при подключении бэкенда сервисы нужно перевести на `HttpClient` и асинхронность.
