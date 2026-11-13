# Требования к бэкенду «Заодно» (по текущему фронтенду)

Источник требований — фронтенд `frontend/apps/main` на моках (`ClubsService`, `AuthService`),
плюс решения владельца продукта (раздел 1). Документ разбит на **рабочие группы** (раздел 4),
каждая рассчитана на отдельного субагента. Порядок запуска — раздел 5.

> Контекст проекта — `CLAUDE.md`. Правила бэкенда — `backend/AGENTS.md` (обязательны).
> Документ «Требования к MVP-прототипу…» в корне **не** является источником для этой задачи.

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
| POST | `/api/auth/refresh` | cookie refresh → `200 { accessToken }` + новая cookie |
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
                  "days": ["Вт","Чт","Сб"], "timeFrom": "15:30", "timeTo": "17:30",
                  "slots": [ … ] } ],                                                // ★ slots
  "enrollments": [ { "id": "<uuid>", "personName": "Петрова Анна", "childName": "Маша",
                     "branchId": "<uuid>", "branchName": "Основной",                 // ★ branchId
                     "enrolledAt": "2026-10-20", "status": "pending" } ]             // ★ status
}
```
- `days/timeFrom/timeTo` филиала — для совместимости: дни всех слотов и время первого слота.
- `enrollments` — заявки в связанный `Club` (пусто, пока активность не одобрена), кроме `cancelled`.
  `branchId` здесь — id филиала **кружка**; соответствие филиалов активности и кружка сохраняется при одобрении.
- Организатор видит только имя заявителя и имя ребёнка (контакты — открытый вопрос).
- Подтверждать/отклонять может владелец кружка; для кружков без владельца — только `ROLE_ADMIN`
  (voter `EnrollmentVoter`). Недопустимый переход → `409`.

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
