COMPOSE := docker compose
# Одноразовый контейнер бэкенда без MySQL — для команд, которым не нужна БД
BACKEND_RUN  := $(COMPOSE) run --rm --no-deps backend
# Запущенный контейнер бэкенда (после make start) — для команд с БД
BACKEND_EXEC := $(COMPOSE) exec backend
FRONTEND_RUN := $(COMPOSE) run --rm --no-deps frontend

.PHONY: install build start stop \
	backend-deps jwt-keys console \
	db-migration db-migrate db-fixtures db-reset test-db \
	lint cs-fix typecheck test check

install:
	$(COMPOSE) run --rm --no-deps frontend corepack yarn install
	$(COMPOSE) run --rm --no-deps backend composer install

# Полная сборка: зависимости (install), образы, production-версия фронта в ./public
build: install
	$(COMPOSE) build
	$(COMPOSE) run --rm --no-deps frontend corepack yarn build

start:
	@test -d frontend/node_modules -a -d backend/vendor || { echo "Зависимости не установлены — сначала выполните make install"; exit 1; }
	$(COMPOSE) up -d --no-build

stop:
	$(COMPOSE) down

# ── Бэкенд: пакеты и утилиты ───────────────────────────────────────────

# Разовая установка пакетов из docs/backend-requirements.md (группа A).
# Flex-рецепты сами создадут конфиги; результат нужно закоммитить.
backend-deps:
	$(BACKEND_RUN) composer require \
		symfony/orm-pack symfony/serializer-pack symfony/validator symfony/security-bundle \
		symfony/uid symfony/string symfony/workflow symfony/rate-limiter \
		lexik/jwt-authentication-bundle gesdinet/jwt-refresh-token-bundle
	$(BACKEND_RUN) composer require --dev \
		symfony/test-pack doctrine/doctrine-fixtures-bundle dama/doctrine-test-bundle \
		friendsofphp/php-cs-fixer

# Ключи JWT для dev (config/jwt/, в git не попадают); существующие не перезаписываются
jwt-keys:
	$(BACKEND_RUN) bin/console lexik:jwt:generate-keypair --skip-if-exists

# Произвольная команда консоли: make console c="debug:router"
console:
	$(BACKEND_EXEC) bin/console $(c)

# ── База данных (нужен make start) ─────────────────────────────────────

# Сгенерировать миграцию по изменениям сущностей
db-migration:
	$(BACKEND_EXEC) bin/console doctrine:migrations:diff --no-interaction

# Применить миграции к dev-базе
db-migrate:
	$(BACKEND_EXEC) bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Перезалить фикстуры в dev-базу (данные удаляются)
db-fixtures:
	$(BACKEND_EXEC) bin/console doctrine:fixtures:load --no-interaction

# Пересоздать dev-базу с нуля: удалить все таблицы, применить миграции, залить фикстуры
db-reset:
	$(BACKEND_EXEC) bin/console doctrine:schema:drop --full-database --force
	$(MAKE) db-migrate db-fixtures

# Подготовить тестовую базу activities_test: создать (от root — у пользователя
# activities нет прав на другие базы), применить миграции, залить фикстуры
test-db:
	$(COMPOSE) exec mysql mysql -uroot -proot -e \
		"CREATE DATABASE IF NOT EXISTS activities_test; GRANT ALL ON activities_test.* TO 'activities'@'%';"
	$(BACKEND_EXEC) bin/console --env=test doctrine:schema:drop --full-database --force
	$(BACKEND_EXEC) bin/console --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(BACKEND_EXEC) bin/console --env=test doctrine:fixtures:load --no-interaction

# ── Проверки ───────────────────────────────────────────────────────────

# Статические проверки бэкенда, БД не нужна
lint:
	$(BACKEND_RUN) bin/console lint:container
	$(BACKEND_RUN) bin/console lint:yaml config --parse-tags
	$(BACKEND_RUN) bin/console doctrine:schema:validate --skip-sync
	$(BACKEND_RUN) vendor/bin/php-cs-fixer fix --dry-run --diff

# Автоисправление стиля бэкенда (@Symfony)
cs-fix:
	$(BACKEND_RUN) vendor/bin/php-cs-fixer fix

# Проверка типов фронтенда
typecheck:
	$(FRONTEND_RUN) corepack yarn typecheck

# Тесты бэкенда (нужны make start и make test-db)
test:
	$(BACKEND_EXEC) vendor/bin/phpunit

# Всё сразу: линт, типы фронта, тесты
check: lint typecheck test
