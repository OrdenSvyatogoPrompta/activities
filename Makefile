COMPOSE := docker compose

.PHONY: install build start stop

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
