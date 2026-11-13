# Production-образ бэкенда: Symfony на php-fpm.
# Контекст сборки — корень репозитория.

# Зависимости ставим в отдельном образе: composer в итоговый образ не попадает
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress
COPY backend/ ./
RUN composer dump-autoload --no-dev --classmap-authoritative

FROM php:8.5-fpm-alpine

RUN docker-php-ext-install pdo_mysql \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

ENV APP_ENV=prod
WORKDIR /var/www/backend

# Код принадлежит root и только читается; писать можно лишь в var/
COPY --from=vendor /app ./
RUN mkdir -p var && chown www-data:www-data var

USER www-data
