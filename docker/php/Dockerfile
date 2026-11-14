FROM php:8.5-fpm-alpine
# intl — транслитерация slug; icu-dev нужен только на время сборки
RUN apk add --no-cache icu-libs \
    && apk add --no-cache --virtual .build-deps icu-dev \
    && docker-php-ext-install pdo_mysql intl \
    && apk del .build-deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
