# Требования к бэкенду «Заодно» (по текущему фронтенду)

Источник требований — фронтенд `frontend/apps/main` на моках (`ClubsService`, `AuthService`),
плюс решения владельца продукта (раздел 1). Документ разбит на **рабочие группы** (раздел 4),
каждая рассчитана на отдельного субагента. Порядок запуска — раздел 5.

> Контекст проекта — `CLAUDE.md`. Правила бэкенда — `backend/AGENTS.md` (обязательны).
> Документ «Требования к MVP-прототипу…» в корне **не** является источником для этой задачи.

> **Статус на 2026-11-13** (подробности и чеклист ручной проверки — раздел 8):
> | Группа | Статус | Ветка |
> |---|---|---|
> | A — Платформа | ✅ код написан, **не проверен запуском** | `feat/backend-platform` |
> | I — Интеграция фронтенда (I1–I3) | ✅ код написан, **не проверен запуском** | `feat/frontend-api-integration` |
> | B — Модель, миграции, фикстуры | ✅ код написан, **не проверен запуском** | `feat/backend-domain-model` (от A) |
> | C — Аутентификация и профиль | ✅ код написан, **не проверен запуском** | `feat/backend-c-auth` (от B) |
> | D — Каталог | ✅ код написан, **не проверен запуском** | `feat/backend-d-catalog` (от B) |
> | E — Дети | ✅ код написан, **не проверен запуском** | `feat/backend-e-children` (от B) |
> | F — Заявки пользователя | ✅ код написан, **не проверен запуском** | `feat/backend-f-enrollments` (от B) |
> | G — Активности организатора | ✅ код написан, **не проверен запуском** | `feat/backend-g-activities` (от B) |
> | H — Модерация | ✅ код написан, **не проверен запуском** | `feat/backend-h-moderation` (от B) |
>
> Ветки не слиты в `main` и не запушены. Пробное слияние C–H поверх B — без конфликтов (раздел 8.5).

---

## 1. Принятые решения

| # | Вопрос | Решение |
|---|---|---|
| D1 | Каталог и «мои активности» | **Две сущности.** `Club` — опубликованный кружок каталога. `Activity` — заявка организатора на кружок, созданная в ЛК. Активность проходит **модерацию**; после одобрения создаётся `Club` (владелец = автор активности), и он появляется в каталоге |
| D2 | Модерация | Роль `ROLE_ADMIN`, модерация через HTTP API. UI админки — вне рамок (отдельная задача) |
| D3 | Аутентификация | **JWT**: короткоживущий access-токен + refresh-токен |
| D4 | Фильтрация каталога | **На клиенте.** API отдаёт весь опубликованный каталог одним списком, фронт фильтрует, как сейчас |
| D5 | Запись в кружок | **Заявка с подтверждением**: `pending → confirmed / rejected`, отмена пользователем → `cancelled` |
| D6 | Кто подтверждает заявки | Владелец кружка; заявки в кружки без владельца (сидовые) — `ROLE_ADMIN`. Админ может подтверждать любые |
| D7 | Данные моков | Переносятся в БД **Doctrine-фикстурами** (только dev/test): 18 кружков, демо-пользователь, админ |

Ответы на вопросы из `backend/AGENTS.md` («Ask before generating»), чтобы субагенты не останавливались:
- **Persistence:** Doctrine ORM + Doctrine Migrations, MySQL 8.4.
- **Interface:** только JSON API (Serializer + Validator), без Twig и без API Platform.
- **Auth:** SecurityBundle + `lexik/jwt-authentication-bundle` + `gesdinet/jwt-refresh-token-bundle`.

---

## 2. Общие соглашения API

- Все маршруты под `/api` (так настроен nginx). Формат — JSON, ключи в `camelCase`.
- Фронт и API на одном домене, CORS не нужен.
- **Идентификаторы:** UUID v7 (`symfony/uid`) для всех сущностей, строкой. Исключение — `Club`:
  в API его `id` — это **slug** (`swimming-dyush`), потому что фронт строит URL `/club/:id`.
  Для одобренных активностей slug генерируется из названия (`AsciiSlugger`, транслитерация), уникальность через суффикс `-2`, `-3`.
- **Даты:** дата — `YYYY-MM-DD` (`enrolledAt`), момент времени — ISO 8601 с таймзоной (`createdAt`).
  Таймзона приложения — `Asia/Vladivostok`.
- **Время занятий:** `HH:MM`.
- **Перечисления** — те же значения, что во фронте, чтобы не делать маппинг:
  - `category`: `Спорт | Творчество | Наука | Музыка | Танцы`
  - `day`: `Пн | Вт | Ср | Чт | Пт | Сб | Вс`
  - `priceType`: `free | pushkin | paid`
  - `visitType`: `recurring | oneTime | both`
  - `gender`: `male | female`
  - `enrollmentStatus`: `pending | confirmed | rejected | cancelled`
  - `activityStatus`: `pending | approved | rejected`
- **Ошибки** — `application/problem+json` (RFC 7807), тексты на русском:
  ```json
  { "type": "about:blank", "title": "Ошибка валидации", "status": 422,
    "detail": "…", "violations": [ { "propertyPath": "email", "message": "Email уже занят" } ] }
  ```
  Коды: `400` — некорректный JSON, `401` — нет/просрочен токен, `403` — нет прав,
  `404` — не найдено, `409` — конфликт состояния (дубль заявки, недопустимый переход статуса),
  `422` — ошибки валидации.
- **Авторизация запросов:** `Authorization: Bearer <access>`.
- Запросы привязываются к DTO через `#[MapRequestPayload]` / `#[MapQueryString]`, ответы — через Serializer
  (группы сериализации или отдельные readonly-DTO ответа). Контроллеры тонкие, логика в сервисах.
- Поля для отображения (`price`, `age`, `schedule`) **формирует сервер**, фронт показывает их как есть.

---

## 3. Доменная модель

```
User 1─* Child
User 1─* Enrollment *─1 Club
                    *─0..1 Branch
                    *─0..1 Child           (null = «для себя»)
User 1─* Activity 1─* ActivityBranch 1─* ScheduleSlot
Activity 0..1─0..1 Club                    (после одобрения)
User 0..1─* Club (owner; null = сидовый, «бесхозный»)
Club 1─* Branch 1─* ScheduleSlot
```

### User
`id`, `email` (уникальный, в нижнем регистре), `password` (хеш), `name`, `roles` (`ROLE_USER`, опц. `ROLE_ADMIN`), `createdAt`.

### Child
`id`, `parent` (User), `firstName` (обязательно, 1–50), `lastName` (опц., до 50), `age` (1–18, целое), `gender`, `createdAt`.
> Фронт хранит возраст, а не дату рождения. Оставляем `age` (см. открытые вопросы).

### Club (каталог)
`id`, `slug` (уникальный), `name`, `categories` (1+ из перечисления), `emoji`, `color` (`#rrggbb`),
`shortDescription` (до 160), `fullDescription`, `ageMin`, `ageMax`, `visitType`,
`priceType`, `priceAmount` (целое, ₽/мес; `0` для `free`), `tags` (строки), `isPublished` (bool),
`owner` (User | null), `sourceActivity` (Activity | null), `createdAt`, `updatedAt`.

### Branch (филиал кружка)
`id`, `club`, `name`, `address`, `phone`, `scheduleNote` (свободный текст, опц., напр. «По договорённости»),
`position` (порядок), `slots` (ScheduleSlot[]).
**У каждого кружка в БД минимум один филиал.** Сидовые кружки без филиалов получают один филиал из
своих `address/phone/schedule`; его имя — `«Основной»`.

### ScheduleSlot
`day`, `timeFrom`, `timeTo` (`timeFrom < timeTo`). Принадлежит Branch или ActivityBranch.

### Activity (заявка организатора)
`id`, `owner` (User), `name`, `category` (одна), `emoji` (по умолчанию `🎯`), `description`,
**поля, нужные для публикации в каталоге:** `ageMin`, `ageMax`, `visitType`, `priceType`, `priceAmount`, `tags` (опц.);
`status` (`pending` при создании), `moderationComment` (обязателен при `rejected`), `moderatedAt`, `moderatedBy`,
`club` (Club | null, заполняется при одобрении), `createdAt`.

### ActivityBranch
`id`, `activity`, `name` (по умолчанию `«Основной»`), `address`, `phone`, `slots` (ScheduleSlot[]).

### Enrollment (заявка на запись)
`id`, `user`, `club`, `branch` (null допустим, только если у кружка один филиал — тогда сервер подставляет его сам),
`child` (null = «для себя»), `status`, `rejectReason` (опц.), `createdAt` (→ `enrolledAt`), `statusChangedAt`.

**Правила:**
- Нельзя иметь две **активные** (`pending|confirmed`) заявки на один кружок для одного участника
  (участник = пользователь «для себя» или конкретный ребёнок) → `409`.
- Переходы статусов: `pending → confirmed | rejected | cancelled`, `confirmed → cancelled`. Остальные → `409`.
  Рекомендуется `symfony/workflow` (state machine) — тогда правила в одном месте.
- `confirmed/rejected` — владелец кружка или `ROLE_ADMIN`; `cancelled` — только автор заявки.
- Ребёнок в заявке должен принадлежать автору заявки → иначе `422`.
- Запись в неопубликованный кружок запрещена → `404`.

### Производные поля (считает сервер)
- `price`: `free` → `Бесплатно`; иначе `2 500 ₽ / мес` (разряды через обычный пробел, как в моках).
- `age`: `6–18 лет` (длинное тире). Склонение «год/года/лет» — по последнему числу.
- `schedule` филиала: слоты с одинаковым временем группируются: `Пн, Ср, Пт · 15:00–18:00`; группы через `\n`.
  Если задан `scheduleNote` — возвращается он.
- `days` кружка — объединение дней всех филиалов, в порядке `Пн…Вс`.
- `color` для одобренной активности — по категории: `Спорт #0ea5e9`, `Творчество #f59e0b`, `Наука #6366f1`, `Музыка #ec4899`, `Танцы #8b5cf6`.
- `shortDescription` для одобренной активности — первое предложение `description`, обрезка до 160 символов.

---

## 4. Рабочие группы

Каждая группа: что сделать, контракт, правила, критерии готовности. Во всех группах:
функциональные тесты `WebTestCase` на каждый эндпоинт (успех + основные ошибки + права),
`bin/console lint:container`, стиль `@Symfony`. Работа в своей ветке по скиллу `branch`,
коммиты по скиллу `commit`.

### Группа A — Платформа (блокирующая, первой)

**Цель:** поставить и настроить инфраструктуру, на которую опираются все остальные.

1. Пакеты (`composer require`, через Flex-рецепты):
   - `symfony/orm-pack` (Doctrine ORM + Migrations), `symfony/serializer-pack`, `symfony/validator`,
     `symfony/security-bundle`, `symfony/uid`, `symfony/string`, `symfony/workflow`, `symfony/rate-limiter`,
     `lexik/jwt-authentication-bundle`, `gesdinet/jwt-refresh-token-bundle`;
   - dev: `symfony/test-pack`, `doctrine/doctrine-fixtures-bundle`, `dama/doctrine-test-bundle`,
     `symfony/maker-bundle`, `friendsofphp/php-cs-fixer`.
2. Docker: в `docker/php/Dockerfile` и `docker/prod/backend.Dockerfile` добавить расширение `intl`
   (транслитерация slug). Проверить, что `pdo_mysql` и `openssl` на месте.
3. `DATABASE_URL` для `test`-окружения (отдельная БД `activities_test`), `.env.test`.
4. Security: firewall `api` на `^/api`, stateless, JWT-аутентификатор; публичные маршруты:
   `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/refresh`, `GET /api/clubs*`, `GET /api/ping`.
   Остальное `/api` — `IS_AUTHENTICATED_FULLY`; `/api/admin` — `ROLE_ADMIN`.
   (User provider подключается в группе B, когда появится сущность.)
5. JWT: TTL access — 15 мин, refresh — 30 дней, ротация refresh при обновлении (single-use).
   Refresh-токен передаётся **в HttpOnly cookie** (`Secure`, `SameSite=Strict`, `Path=/api/auth`), не в теле.
   Ключи: в dev генерируются `lexik:jwt:generate-keypair`, в prod — из переменных окружения
   (контейнер read-only, генерировать на лету нельзя).
6. Единый обработчик исключений → `problem+json` (раздел 2), включая ошибки валидации `MapRequestPayload`
   и ошибки аутентификации (401 вместо редиректа/HTML).
7. Таймзона `Asia/Vladivostok`, `php-cs-fixer` с `@Symfony`, скрипт/таргет для запуска тестов.
8. Prod (`docs/deploy-checklist.md`, `.github/workflows/deploy.yml`, `docker-compose.prod.yml`):
   - шаг `doctrine:migrations:migrate --no-interaction` при деплое;
   - секреты `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE` в `.env` прод-сервера;
   - обновить чеклист.

**Готово, когда:** `make build && make start` поднимаются, `/api/ping` работает, тестовый прогон
(хотя бы smoke-тест `/api/ping` и 401 на защищённом маршруте) зелёный, ошибки отдаются в `problem+json`.

### Группа B — Доменная модель, миграции, фикстуры (после A, блокирующая)

**Цель:** все сущности раздела 3 одной группой, чтобы параллельные группы не конфликтовали в миграциях.

1. Сущности и репозитории: `User`, `Child`, `Club`, `Branch`, `ScheduleSlot` (для Branch и ActivityBranch),
   `Activity`, `ActivityBranch`, `Enrollment`. Индексы: уникальные `user.email`, `club.slug`; индексы по
   `enrollment(club_id, status)`, `enrollment(user_id)`, `activity(status)`.
2. Одна миграция со всей схемой.
3. User provider для Security (по email), хешер паролей `auto`.
4. Workflow `enrollment` (state machine) с переходами из раздела 3 + guard-выражения/voter на права.
5. Сервисы производных полей: `PriceFormatter`, `AgeFormatter`, `ScheduleFormatter`, `SlugGenerator` —
   с unit-тестами (в т. ч. группировка расписания и склонение «лет»).
6. Фикстуры (dev/test):
   - 18 кружков из `frontend/apps/main/src/app/services/clubs.service.ts` (перенести дословно, `id` моков = `slug`);
     расписания из строк `schedule` разобрать в слоты вручную; «Гитара с нуля» — `scheduleNote: По договорённости`;
     все сидовые кружки без владельца, `isPublished = true`;
   - демо-пользователь `maria@example.com` / `password`, имя «Иванова Мария», дети Артём (8, м) и Соня (5, ж),
     заявки: Артём → `swimming-dyush` филиал «Борисенко» (`confirmed`), Соня → `gimnastika-voskhod` (`pending`);
   - у демо-пользователя одобренная активность «Акварель» (из `MOCK_USER.activities`) со связанным кружком
     и 4 входящими заявками от отдельных фикстурных пользователей (Петрова Анна, Сидоров Игорь, Козлова Вера, Новикова Елена);
   - одна активность в статусе `pending` у демо-пользователя (для проверки модерации);
   - админ `admin@example.com` / `admin`, `ROLE_ADMIN`.

**Готово, когда:** `doctrine:migrations:migrate` и `doctrine:fixtures:load` проходят на чистой БД,
`doctrine:schema:validate` зелёный, unit-тесты форматтеров зелёные.

### Группа C — Аутентификация и профиль (после B)

| Метод | Путь | Тело / ответ |
|---|---|---|
| POST | `/api/auth/register` | `{ name, email, password }` → `201 { accessToken, user }` + cookie refresh |
| POST | `/api/auth/login` | `{ email, password }` → `200 { accessToken, user }` + cookie refresh |
| POST | `/api/auth/refresh` | cookie refresh → `200 { accessToken, user }` + новая cookie |
| POST | `/api/auth/logout` | инвалидирует refresh, чистит cookie → `204` |
| GET | `/api/me` | `200 user` |
| PATCH | `/api/me` | `{ name? }` → `200 user` (на фронте пока нет, но дешево и нужно) |

`user`: `{ id, name, email, roles, stats: { enrollments, activities, children } }` — `stats` для счётчиков
в шапке ЛК (считаются по активным заявкам, всем активностям, детям).

Правила: email валидный, уникальный (`422`), нормализуется в нижний регистр; пароль ≥ 8 символов;
имя 1–100 (фронт сейчас позволяет пустое — сервер требует, фронт доработать).
Ошибка входа — единое сообщение «Неверный email или пароль» (`401`). Ограничение попыток входа —
`login_throttling` (5 попыток / минута на email+IP).

Плюс консольная команда `app:user:promote <email> [--admin]` — выдача роли админа (нужна на проде).

**Готово, когда:** весь цикл register → me → refresh → logout → refresh(401) покрыт тестами.

### Группа D — Каталог (после B)

| Метод | Путь | Ответ |
|---|---|---|
| GET | `/api/clubs` | `200 Club[]` — все опубликованные, сортировка по `name` |
| GET | `/api/clubs/{slug}` | `200 Club` / `404` (в т. ч. неопубликованный) |
| GET | `/api/dictionaries` | `200 { categories, days, priceTypes, visitTypes }` (опц., низкий приоритет) |

`Club` в API (совместим с `models/club.model.ts`, отличия помечены ★):
```json
{
  "id": "swimming-dyush",
  "name": "Плавание · ДЮСШ №1",
  "categories": ["Спорт"],
  "emoji": "🏊", "color": "#0ea5e9",
  "shortDescription": "…", "fullDescription": "…",
  "age": "6–18 лет", "ageMin": 6, "ageMax": 18,
  "days": ["Пн","Вт","Ср","Чт","Пт"],
  "visitType": "recurring",
  "schedule": "Пн, Ср, Пт · 15:00–18:00\nВт, Чт · 10:00–12:00",
  "address": "ул. Борисенко, 100",
  "phone": "+7 423 222-11-00",
  "price": "Бесплатно", "priceAmount": 0, "priceType": "free",
  "tags": ["плавание","спорт","бассейн"],
  "branches": [
    { "id": "<uuid>", "name": "Борисенко", "address": "…", "phone": "…",
      "schedule": "…", "days": ["Пн","Вт","Ср","Чт","Пт"],
      "slots": [ { "day": "Пн", "timeFrom": "15:00", "timeTo": "18:00" } ] }  // ★ slots — новое поле
  ]
}
```
★ `branches` возвращается **всегда** (≥ 1 элемент). `address/phone/schedule` верхнего уровня — данные
первого филиала (для совместимости).

Список не пагинируется (решение D4). Ответ кешируется: `Cache-Control: public, max-age=60` + `ETag`.

**Готово, когда:** ответы для всех 18 фикстурных кружков совпадают с моками по полям из `club.model.ts`
(тест сравнения со снимком), неопубликованный кружок даёт 404 в обоих эндпоинтах.

### Группа E — Дети (после B)

| Метод | Путь | Тело / ответ |
|---|---|---|
| GET | `/api/me/children` | `200 Child[]` |
| POST | `/api/me/children` | `{ firstName, lastName?, age, gender }` → `201 Child` |
| PATCH | `/api/me/children/{id}` | частичное обновление → `200 Child` (на фронте пока нет) |
| DELETE | `/api/me/children/{id}` | `204`; если есть активные заявки → `409` «Сначала отмените записи ребёнка» |

`Child`: `{ id, firstName, lastName, age, gender }`. Чужой ребёнок → `404`.

### Группа F — Заявки пользователя (после B; доступ к ребёнку — по сущности из B, от E не зависит)

| Метод | Путь | Тело / ответ |
|---|---|---|
| POST | `/api/enrollments` | `{ clubId (slug), branchId?, childId? }` → `201 Enrollment` (`status: pending`) |
| GET | `/api/me/enrollments` | `200 Enrollment[]`; `?status=` (по умолчанию все, кроме `cancelled`) |
| POST | `/api/enrollments/{id}/cancel` | → `200 Enrollment` (`cancelled`) |

`Enrollment` (совместим с `Enrollment` из `auth.service.ts`, ★ — новое):
```json
{
  "id": "<uuid>", "clubId": "swimming-dyush", "clubName": "Плавание · ДЮСШ №1",
  "branchId": "<uuid>", "branchName": "Борисенко",
  "schedule": "Пн, Ср, Пт · 15:00–18:00", "days": ["Пн","Ср","Пт"], "color": "#0ea5e9",
  "forChild": { "id": "…", "firstName": "Артём", "lastName": "Иванов", "age": 8, "gender": "male" },
  "enrolledAt": "2026-10-15",
  "status": "pending",        // ★
  "rejectReason": null        // ★
}
```
Правила: см. раздел 3 (дубли → `409`, `branchId` обязателен, если у кружка > 1 филиала, иначе подставляется
единственный; `branchId` должен принадлежать кружку → `422`). Отмена — через workflow.

### Группа G — Активности организатора и входящие заявки (после B)

| Метод | Путь | Тело / ответ |
|---|---|---|
| GET | `/api/me/activities` | `200 Activity[]` (свои, все статусы) |
| GET | `/api/me/activities/{id}` | `200 Activity` / `404` для чужой |
| POST | `/api/me/activities` | создание → `201 Activity` (`status: pending`) |
| POST | `/api/enrollments/{id}/confirm` | владелец кружка или админ → `200 Enrollment` |
| POST | `/api/enrollments/{id}/reject` | `{ reason? }` → `200 Enrollment` |

Тело создания:
```json
{
  "name": "Акварель", "category": "Творчество", "emoji": "🎨", "description": "…",
  "ageMin": 5, "ageMax": 14, "visitType": "both",
  "priceType": "pushkin", "priceAmount": 2500, "tags": ["рисование"],
  "branches": [ { "name": "Основной", "address": "пр. Океанский, 44", "phone": "+7 …",
                  "slots": [ { "day": "Вт", "timeFrom": "15:30", "timeTo": "17:30" } ] } ]
}
```
Валидация: `name` 2–100, `category` из перечисления, `ageMin ≤ ageMax` (0–99), `priceAmount ≥ 0`
(для `free` принудительно 0), минимум один филиал, у филиала `address` и минимум один слот,
`timeFrom < timeTo`. ★ Поля возраста, цены, формата, телефона — **новые для фронта** (форма их пока не содержит).

`Activity` в ответе (совместим с `ManagedActivity`, ★ — новое):
```json
{
  "id": "<uuid>", "name": "Акварель", "category": "Творчество", "emoji": "🎨", "description": "…",
  "status": "approved", "moderationComment": null, "clubId": "akvarel",            // ★
  "ageMin": 5, "ageMax": 14, "visitType": "both", "priceType": "pushkin", "priceAmount": 2500, "tags": [], // ★
  "branches": [ { "id": "<uuid>", "name": "Основной", "address": "…", "phone": "…",
                  "schedule": "Вт, Чт, Сб · 15:30–17:30", "slots": [ … ] } ],         // ★ slots
  "enrollments": [ { "id": "<uuid>", "personName": "Петрова Анна", "childName": "Маша",
                     "activityBranchId": "<uuid>", "branchName": "Основной",         // ★ activityBranchId
                     "enrolledAt": "2026-10-20", "status": "pending" } ]             // ★ status
}
```
- `enrollments` — заявки в связанный `Club` (пусто, пока активность не одобрена), кроме `cancelled`.
  `activityBranchId` — id филиала **активности**, из которого создан филиал кружка заявки (соответствие
  сохраняется при одобрении).
- Организатор видит только имя заявителя и имя ребёнка (контакты — открытый вопрос).
- Подтверждать/отклонять может владелец кружка; для кружков без владельца — только `ROLE_ADMIN`
  (voter `EnrollmentVoter`). Для confirm/reject/cancel одно правило: кто не видит заявку (не автор,
  не владелец кружка, не админ) → `404`; видит, но не вправе → `403`; недопустимый переход → `409`.

### Группа H — Модерация (после B; использует права из A)

| Метод | Путь | Тело / ответ |
|---|---|---|
| GET | `/api/admin/activities` | `?status=pending` (по умолчанию) → `200 Activity[]` + `owner { id, name, email }` |
| POST | `/api/admin/activities/{id}/approve` | → `200 Activity` (`approved`, `clubId` заполнен) |
| POST | `/api/admin/activities/{id}/reject` | `{ comment }` (обязателен) → `200 Activity` |
| GET | `/api/admin/enrollments` | `?status=pending&ownerless=true` → `200` заявки с `club` и заявителем |
| PATCH | `/api/admin/clubs/{slug}` | `{ isPublished }` — снять/вернуть публикацию (опц.) |

Одобрение — в транзакции: создать `Club` (slug, `owner` = автор, `categories = [category]`, `color` по категории,
`shortDescription`/`fullDescription` из `description`, филиалы и слоты копией из `ActivityBranch`,
`isPublished = true`), связать `activity.club`, проставить `moderatedAt/By`.
Одобрять/отклонять можно только `pending` → иначе `409`. Повторная подача и редактирование после
одобрения — вне рамок MVP.

Подтверждение/отклонение заявок админом — эндпоинты группы G (`/api/enrollments/{id}/confirm|reject`),
здесь только список.

### Группа I — Интеграция фронтенда (после C–H; может стартовать с C и D)

Не бэкенд, но без неё API не используется. Разбивается на I1–I3, их можно выполнять параллельно после I1.

**I1. HTTP-слой и авторизация**
- `ApiClient` поверх `HttpClient` с базой `environment.apiUrl`, `provideHttpClient(withInterceptors(...), withFetch())`.
- Интерцептор: `Authorization: Bearer`, на `401` — один `POST /api/auth/refresh` и повтор запроса,
  при неудаче — logout и переход на `/auth/login`. Запросы с `withCredentials` для cookie refresh.
- `AuthService`: access-токен в памяти; при старте приложения — тихий `refresh` (восстановление сессии).
  `login/register/logout` асинхронные, ошибки сервера показываются в форме (`violations` → поля).
- Регистрация отправляет `name` (сейчас игнорируется), поле становится обязательным.
- `authGuard` на `/personal` (сейчас ЛК сам проверяет `user()`).

**I2. Каталог и карточка кружка**
- `ClubsService` → `GET /api/clubs` (один раз, кеш в signal), `GET /api/clubs/{slug}`; состояния загрузки и ошибки.
- Фильтрация остаётся клиентской, логика `HomeComponent.filteredClubs` не меняется.
- `ClubComponent`: `branches` теперь всегда есть → блок табов показывать при `branches.length > 1`,
  иначе — блок «Контакты и расписание» из первого филиала.
- Запись: `POST /api/enrollments`; модалка успеха — «Заявка отправлена, ждите подтверждения»;
  обработка `409` (уже есть заявка). `alreadyEnrolled` считать по участнику (себя/ребёнок), а не по кружку целиком,
  и учитывать только `pending|confirmed`.

**I3. Личный кабинет**
- Данные: `GET /api/me`, `/api/me/enrollments`, `/api/me/children`, `/api/me/activities` вместо `MOCK_USER`.
- «Мои записи»: бейдж статуса (`На рассмотрении / Подтверждена / Отклонена` + причина); отмена через `/cancel`;
  календарь — только `confirmed` (или `pending` бледнее).
- «Активности»: бейдж статуса модерации и комментарий при отклонении; в форме создания — новые поля
  (возраст от/до, формат, тип и сумма цены, телефон филиала, теги, несколько слотов расписания);
  во входящих заявках — статус и кнопки «Подтвердить / Отклонить»; группировка по `branchId`, не по имени.
- «Дети»: CRUD через API, обработка `409` при удалении.

**Готово, когда:** `yarn build` и `yarn typecheck` проходят, моковые данные удалены из сервисов,
сценарии из раздела 6 проходят руками в браузере.

---

## 5. Порядок запуска субагентов

```
Волна 1:  A (платформа)
Волна 2:  B (модель + миграция + фикстуры)
Волна 3:  C  D  E  F  G  H        ← параллельно, каждый в своей ветке от B
Волна 4:  I1 → (I2 ∥ I3)          ← можно начать I1 после C, I2 после D
```

Правила для параллельной волны 3:
- **Новые миграции в волне 3 запрещены.** Если группе не хватает поля — она останавливается и сообщает;
  правка схемы идёт отдельным коммитом в B-ветку, потом остальные ребейзятся.
- Каждая группа трогает только свои файлы: `src/Controller/<Group>/`, `src/Dto/<Group>/`, `src/Service/<Group>/`,
  `tests/Functional/<Group>/`. Общие файлы (`security.yaml`, `workflow.yaml`, сущности) меняет только A/B.
- G и H используют общий voter заявок; его пишет **G**, H только читает.
- Слияние в `main` — по одной ветке, `--no-ff`, после зелёных тестов.

Шаблон задания субагенту:
> Ты реализуешь группу **<X>** из `docs/backend-requirements.md` в репозитории `activities`.
> Прочитай `CLAUDE.md`, `backend/AGENTS.md`, разделы 1–3 и раздел группы <X>.
> Работай в ветке `feat/backend-<x>-<кратко>` от `<базовая ветка>`. Команды выполняй через `docker compose`
> (см. `CLAUDE.md`). Не трогай файлы других групп и не создавай миграций.
> Готово = критерии группы выполнены и `bin/phpunit` зелёный. Коммить по скиллу `commit`, не пушь.
> В конце верни: список эндпоинтов, отклонения от контракта (если были) и причины, открытые вопросы.

---

## 6. Сквозные сценарии приёмки

1. Гость открывает каталог → 18 кружков, фильтры работают как на моках → карточка кружка → «Записаться» → редирект на вход.
2. Регистрация → ЛК пуст → добавить ребёнка → записать ребёнка в кружок с филиалами → в «Мои записи» заявка «На рассмотрении».
3. Админ видит заявку в `/api/admin/enrollments?ownerless=true` → подтверждает → у пользователя статус «Подтверждена», запись в календаре.
4. Повторная запись того же ребёнка в тот же кружок → `409`, на фронте понятное сообщение.
5. Пользователь создаёт активность → статус «На модерации», в каталоге её нет → админ одобряет → кружок появляется в каталоге, у автора — ссылка на него.
6. Другой пользователь записывается в этот кружок → автор видит входящую заявку → подтверждает.
7. Админ отклоняет другую активность с комментарием → автор видит статус и комментарий.
8. Access-токен истёк → запрос прозрачно обновляется через refresh; после logout refresh не работает.

---

## 7. Открытые вопросы

1. **Возраст ребёнка** — хранить `age` (как сейчас) или `birthDate` (возраст не устаревает)? Сейчас — `age`.
2. **Контакты заявителя** для организатора: показывать email? Добавить телефон пользователю?
3. **Удаление ребёнка с активными заявками** — сейчас `409`; альтернатива — каскадная отмена заявок.
4. **Редактирование** активности/кружка организатором после одобрения (повторная модерация?) — вне MVP.
5. **Уведомления** (вкладка «Уведомления» в устаревшем `apps/personal`; письма о статусе заявки) — вне MVP.
   Если понадобятся письма, prod-бэкенду нужен выход в интернет (сейчас сеть `internal`).
6. **Перечисления на русском** в API (решение для скорости интеграции). Если появятся другие клиенты —
   перейти на коды (`sport`, `mon`) с локализацией на фронте.
7. Нужна ли **админка UI** для модерации в рамках MVP или достаточно API + Postman/curl.

---

## 8. Ход выполнения

Агенты работали без PHP, Node, Docker и сети: **ни один таргет `make` не запускался**, код проверен
только чтением (конфиги сверены с исходниками в `backend/vendor/`). Всё ниже нужно проверить вручную.

### 8.1. Группа A — Платформа ✅ (не проверено)

Ветка `feat/backend-platform` (от `main` 0bf50a1), 6 коммитов, 21 файл:
```
6c7bb5a test(api): добавил smoke-тесты платформы
a9b50d6 ci(deploy): добавил миграции и ключи JWT в прод-деплой
cc8fd61 build(docker): добавил расширение intl в образы бэкенда
ce8f82f feat(api): задал таймзону Asia/Vladivostok и описал тестовую базу
9965f52 feat(auth): настроил firewall api, JWT и refresh-токен в cookie
f1311f6 feat(api): добавил ответы об ошибках в формате problem+json
```

**Сделано**
- `intl` в обоих Dockerfile бэкенда; в `docker-compose.yml` к `DATABASE_URL` добавлен `charset=utf8mb4`.
- Тестовая БД — суффикс `_test` из `doctrine.yaml` (`.env.test` без `DATABASE_URL`).
- `security.yaml`: firewall `api` (`^/api`, stateless, `entry_point: jwt`), `json_login` на `/api/auth/login`,
  `login_throttling` 5/мин, `refresh_jwt` на `/api/auth/refresh`, `logout` на `/api/auth/logout`,
  `access_control` по п. 4, `role_hierarchy ROLE_ADMIN → ROLE_USER`.
- Маршрут refresh рецепта (`/api/token/refresh`, YAML) удалён; маршруты auth — атрибутами в
  `src/Controller/Security/AuthFirewallController.php`.
- JWT: access 900 с, refresh 30 дней, single-use; refresh в cookie `refresh_token`
  (HttpOnly, Secure, SameSite=Strict, Path=/api/auth), из тела ответа убран; в prod ключи — содержимым PEM.
- problem+json: `src/Http/Problem/*`, `src/EventListener/ProblemExceptionListener.php`,
  `src/EventListener/AuthenticationFailureProblemListener.php` (401 / 429 при throttling).
- Таймзона: `Kernel::boot()` по `APP_TIMEZONE` (по умолчанию `Asia/Vladivostok`).
- Деплой: JWT-секреты в `.env` сервера (с проверкой на пустоту), перед перезапуском — `up -d mysql`,
  ожидание `mysqladmin ping`, `doctrine:migrations:migrate`; `docker-compose.prod.yml` передаёт JWT-переменные бэкенду.
- Тесты: `tests/Functional/Platform/PlatformSmokeTest.php`, `tests/Unit/Http/Problem/ProblemFactoryTest.php`;
  тестовый контроллер `tests/Fixtures/Controller/ProtectedProbeController.php` (`/api/_test/protected`,
  `/api/admin/_test/protected`) подключён блоком `when@test` в `config/services.yaml`.

**Отклонения и решения агента**
- Сообщения об ошибках: `symfony/translation` не установлен → сообщения валидатора по умолчанию английские;
  **группам B–H писать `message:` на русском в каждом `#[Assert]`** (или поставить пакет + `default_locale: ru`).
  `detail` берётся из исключения, только если в нём есть кириллица; 5xx не раскрываются; в debug добавляется `debug`.
- Logout работает без access-токена (listener выхода срабатывает до `access_control`, достаточно cookie).
- Не включены опции gesdinet `hash_tokens`, `reuse_detection`, `rate_limiter` — можно позже.
- `composer recipes:update` может вернуть удалённый файл маршрута рецепта.

**Передано следующим группам**
- **B:** в `security.yaml` заменить `users_in_memory` на `entity: { class: App\Entity\User, property: email }`
  (TODO в `providers` и в firewall `api`); в миграцию включить таблицу `refresh_tokens` (`App\Entity\RefreshToken`).
- **C:** lexik отдаёт `{ token }` — нужен слушатель `on_authentication_success` для `{ accessToken, user }` (и для refresh);
  gesdinet на logout отвечает 200 `{code, message}` (без cookie — 400) — нужен слушатель `LogoutEvent` для `204`;
  в тестах с cookie — `HTTPS=on` (cookie `Secure`); состояние throttling в файловом кеше переживает прогоны
  (глобальный лимит 25/мин на IP) — учесть в тестах.

**Изменения для `docs/deploy-checklist.md`** (файл не в git, агент его не трогал)
- Секреты environment `production`: `JWT_SECRET_KEY` (содержимое `private.pem` целиком), `JWT_PUBLIC_KEY`
  (содержимое `public.pem`), `JWT_PASSPHRASE` (непустая). Генерация:
  ```
  openssl genpkey -algorithm RSA -aes256 -pass pass:<фраза> -pkeyopt rsa_keygen_bits:4096 -out private.pem
  openssl pkey -in private.pem -passin pass:<фраза> -pubout -out public.pem
  ```
- Миграции теперь применяются при деплое — отметить пункт.
- Выдача админа на проде — `app:user:promote` (появится в группе C).
- Ротация ключей JWT делает недействительными все выданные токены.

### 8.2. Группа I — Интеграция фронтенда ✅ (не проверено)

Ветка `feat/frontend-api-integration` (от `main` 727aa08 — без коммита с пакетами бэкенда, на фронт не влияет),
3 коммита, 25 файлов, только `frontend/apps/main/**`:
```
4846a35 feat(ui): перевёл личный кабинет на API                    (I3)
f651841 feat(ui): подключил каталог и запись в кружок к API         (I2)
c719472 feat(auth): перевёл авторизацию фронта на HTTP API с JWT    (I1)
```

**Сделано**
- `ApiClient` (`withCredentials`), `provideHttpClient(withInterceptors([authInterceptor]), withFetch())`,
  тихий refresh при старте (`provideAppInitializer`), `authGuard` на `/personal` с `?returnUrl=`,
  разбор problem+json (`api/api-error.ts`), `violations` → ошибки полей формы.
- Моки `MOCK_USER` и `CLUBS` удалены.
- Вызываемые эндпоинты:

  | Сервис | Вызовы |
  |---|---|
  | `AuthService` | `POST /api/auth/login`, `/register`, `/refresh`, `/logout`; `GET /api/me` |
  | `ClubsService` | `GET /api/clubs`, `GET /api/clubs/{slug}` |
  | `EnrollmentsService` | `GET /api/me/enrollments`; `POST /api/enrollments`, `/{id}/cancel`, `/{id}/confirm`, `/{id}/reject` |
  | `ChildrenService` | `GET`, `POST /api/me/children`; `DELETE /api/me/children/{id}` |
  | `ActivitiesService` | `GET`, `POST /api/me/activities` |

**Отклонения и решения агента**
- Карточка кружка берётся из кеша каталога; `GET /api/clubs/{slug}` — только при прямом заходе.
- Пустые POST шлют `{}`; `branchId` в `POST /api/enrollments` отправляется всегда.
- Logout шлёт Bearer (Bearer не шлётся только на login/register/refresh). Провал refresh → `/auth/login`
  даже с публичного каталога (гостя без токена не касается).
- Входящие заявки — отдельный блок, сгруппированный по `branchId` кружка (не вложен под филиалы активности:
  контракт не связывает филиалы активности и кружка).
- Форма активности: один филиал, несколько «групп слотов» (дни + время) → `slots[]`; теги через запятую;
  подпись цены активности собирается на клиенте.
- Счётчики ЛК — по загруженным спискам (записи только `pending|confirmed`), до загрузки — `user.stats`.
- «Уже записан» — по участнику: CTA «✓ Заявка: для себя, Артём», в шите участники с активной заявкой
  отмечены ✓ и заблокированы; «Записаться» скрывается, только когда записаны все.
- Ошибки 409 (запись, отмена, удаление ребёнка) и 401 входа — фиксированные русские тексты, не `detail`.
- Общие стили (`.spinner`, `.state`, `.status-badge`, `.inline-error`) вынесены в `styles/styles.scss`.

### 8.3. Ручная проверка

**Группа A** (ветка `feat/backend-platform`):
1. `make build` — пересобрать образ бэкенда (добавлен `intl`).
2. `make lint` — `lint:container`, `lint:yaml`, маппинг Doctrine, php-cs-fixer (особо: выравнивание phpdoc,
   типизированные константы `const string`).
3. `make start && make jwt-keys && make test-db && make test` — smoke-тесты платформы. Тесты в БД не ходят,
   но DAMA-расширение должно работать без запросов.
4. Вручную: `curl -i localhost:8080/api/ping` → 200; `curl -i localhost:8080/api/me` → 401
   `application/problem+json`; `curl -i localhost:8080/api/nope` → 404 problem+json.
5. Риски конфигов: ключи `refresh_jwt` / `entry_point: jwt` в `security.yaml` (по фабрикам в vendor),
   блок `when@test` в `config/services.yaml`.
6. Прод (при первом деплое): завести 3 JWT-секрета; убедиться, что многострочные PEM в одинарных кавычках
   корректно читаются `docker compose` из `.env` сервера; после деплоя проверить логин.
7. Обновить `CLAUDE.md`: «Миграций в деплое пока нет», «Не установлены Doctrine/Security…», раздел
   «Данные и сервисы (моки)» — устарели после слияния веток.

**Группа I** (ветка `feat/frontend-api-integration`):
1. `make typecheck` и prod-сборка `make build` (`yarn build`).
2. Бюджет `anyComponentStyle` (ошибка при 16 kB): `personal-dashboard.component.scss` ≈ 22.5 kB исходника,
   ≈ 15 kB после минификации — впритык. Если упадёт — вынести `.ie-btn`, `.slot-group`, `.btn-link` в глобальные стили.
3. Шаблоны при `strictTemplates`: `@for (…; track $index; let i = $index)`, `@if (a() || b(); as msg)`,
   `[class]="'status-badge status-badge--' + x"`, `(user.name || user.email)[0]`; `Child.lastName` стал `string | null`.
4. `provideAppInitializer` и `effect()` в конструкторах root-сервисов (сброс данных при выходе).
5. Интерцептор: общий in-flight refresh (`shareReplay` + `finalize`), отсутствие бесконечного цикла на 401.
6. Сценарии раздела 6 — **только после групп B–H** (до этого API отвечает 401/404, фронт покажет ошибки загрузки).
   До бэкенда можно проверить: каталог показывает состояние ошибки, `/personal` редиректит на `/auth/login`,
   формы входа/регистрации показывают ошибку сервера.

### 8.4. Открытые вопросы, появившиеся в ходе работы
1. Сопоставление филиалов активности и кружка при одобрении (G/H): без него входящие заявки нельзя показать
   под филиалами активности. Предложение — хранить в `Branch` ссылку на исходный `ActivityBranch`
   и отдавать `activityBranchId` во входящих заявках.
2. Публичные маршруты: logout на бэке работает без access-токена, фронт шлёт Bearer — совместимо, но
   стоит зафиксировать в контракте.
3. Уводить ли гостя с протухшей сессией на логин с публичного каталога (сейчас — да, как в контракте).
4. Несколько филиалов при создании активности — UI поддерживает один.
5. Ставить ли `symfony/translation` с `default_locale: ru` вместо ручных русских `message:` в валидации.

### 8.5. Группы B–H (волны 2–3) ✅ (не проверено)

Окружение агентов: PHP 8.4 CLI без `vendor` (Packagist/GitHub/Docker Hub закрыты файрволом) и **без БД**
(решение владельца — БД в этой итерации не тестируем). Проверено только `php -l` (по объединённому дереву
C–H — без ошибок), вспомогательными скриптами на заглушках (форматтеры, фикстуры против моков, ответы
каталога против снимка) и чтением кода. Функциональные тесты `WebTestCase` написаны, но **ни разу не запускались**.

| Группа | Ветка | Коммиты |
|---|---|---|
| B | `feat/backend-domain-model` | `29fea2e` сущности и репозитории · `c42af9c` миграция · `273520b` entity-провайдер · `1a19155` workflow заявок · `74e236d` форматтеры и slug · `a52ba7c` фикстуры · `e970b90` публичные справочники, сортировка каталога по slug |
| C | `feat/backend-c-auth` | `eda2469` · `cae3e48` · `bb2bb95` · `f6b3a73` |
| D | `feat/backend-d-catalog` | `b6418a0` · `5630531` |
| E | `feat/backend-e-children` | `539d1f7` · `961ed59` |
| F | `feat/backend-f-enrollments` | `d6199fc` · `0f3c49c` · `32a1154` |
| G | `feat/backend-g-activities` | `b13b689` · `482cd77` · `358d4d0` |
| H | `feat/backend-h-moderation` | `fe8781a` · `3cfc3dd` · `63ccbff` |

Пробное слияние (`git merge-tree`) C → D → E → F → G → H поверх B — без конфликтов; имена и пути маршрутов уникальны.
Порядок слияния в `main`: A → B → C…H (любой) → I.

**Отклонения от контракта**
- **B:** в каталоге 19 кружков — «Акварель» демо-пользователя стала отдельным кружком `akvarel` (владелец Мария,
  2 филиала), сидовый `akvarell` без владельца. `days` у `swimming-dyush` включает «Сб» (объединение филиалов);
  `schedule` верхнего уровня — группы через `\n` (в моках «, » / « / »). У «Гитары с нуля» условные слоты
  10:00–20:00, наружу — `scheduleNote`. Заявка Сони — в филиал «Основной». В `Branch` добавлена ссылка
  `sourceActivityBranch` (ответ на 8.4.1).
- **C:** logout всегда `204` (идемпотентно, даже без cookie); `app:user:promote` без `--admin` ничего не делает;
  вход без `email`/`password` — `400` (поведение `json_login`).
- **D:** `phone` филиала без телефона — `""`; заголовок `Cache-Control: max-age=60, public` (порядок директив Symfony).
- **E:** в PATCH отсутствующее/`null` поле не меняется, кроме `lastName` (переданный `null`/`""` стирает фамилию).
- **F:** `?status=` принимает список через запятую; невидимая заявка в cancel/confirm/reject — `404`, видимая без прав — `403`.
  Дубли защищены `SELECT … FOR UPDATE` строки пользователя.
- **G:** в `Activity` сверх контракта `price`, `age`, у филиала `schedule`, у входящей заявки
  `activityBranchId` (вместо `branchId`; `days/timeFrom/timeTo` филиала и `createdAt` убраны — фронт их не читал);
  `description` обязателен; порядок проверок confirm/reject: 404 → 403 → 409.
- **H:** `PATCH /api/admin/clubs/{slug}` отвечает `{ id, name, isPublished }`; в `club` списка заявок — `isPublished`, `owner`.

**Сделать при слиянии / после первого запуска**
1. Дубли форматов: confirm/reject (G: `Dto/Activity/ModeratedEnrollmentResponse`, `Service/Activity/EnrollmentResponseFactory`)
   заменить на `Dto/Enrollment/EnrollmentResponse` и `Service/Enrollment/EnrollmentTransitionApplier` из F;
   `Dto/Admin/Activity*View` (H) объединить с `Dto/Activity/ActivityResponse` (G); ответ PATCH H — полным `ClubView` из D.
   Отмена в F — перевести на `EnrollmentVoter::CANCEL` из G.
2. Хелперы JWT в тестах продублированы в каждой группе (`tests/Functional/*/…Trait`) — вынести в общий.
3. Проверить по живому `vendor` (сверялось по памяти): API gesdinet **3.1** (в `composer.lock`, агент C ориентировался
   на 1.x), `handleAuthenticationSuccess` lexik, приоритеты слушателей login/refresh/logout, invokable-команду,
   маппинг `refresh_tokens`, `MapQueryString` для `?ownerless=true`, `#[MapRequestPayload]` с `null` по умолчанию
   при пустом теле reject, публичность алиаса `JWTTokenManagerInterface` в тестовом контейнере.
4. Порядок ручной проверки: `make build && make start && make jwt-keys && make lint && make db-reset && make test-db && make test`.

**Новые открытые вопросы**
1. `enrollment.child_id` — `ON DELETE CASCADE`: при удалении ребёнка без активных заявок пропадает история его
   заявок (и у организатора). Нужен ли `SET NULL` / мягкое удаление (правка миграции B)?
2. Дубль активной заявки в БД не защищён (только приложение) — уникальный индекс по генерируемой колонке или `symfony/lock`?
3. Гонка одобрения двух активностей с одинаковым slug даёт `500` (уникальный индекс), а не `409`.
4. ORM 3 закрывает EntityManager после исключения в `wrapInTransaction` (H, 409 при повторном одобрении) — проверить тест.
5. Нет лимита числа детей; нет команды снятия роли админа; контакты заявителя организатору не отдаются (вопрос 7.2).
6. Фронту (I): имя при регистрации обязательно, logout → `204` без тела, «Акварель» теперь два кружка.
