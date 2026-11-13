NODE := docker run --rm -v $(CURDIR):/app -v angular-cache:/app/frontend/.angular -w /app/frontend node:22-alpine

.PHONY: install build start stop

install:
	$(NODE) corepack yarn install

build:
	$(NODE) corepack yarn build

start:
	docker compose up -d --build

stop:
	docker compose down
