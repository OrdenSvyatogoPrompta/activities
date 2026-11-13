# Production-образ фронта: собираем Angular и кладём статику в nginx.
# Контекст сборки — корень репозитория.

FROM node:22-alpine AS build
WORKDIR /app/frontend
COPY frontend/package.json frontend/yarn.lock frontend/.yarnrc.yml ./
RUN corepack yarn install --immutable
COPY frontend/ ./
# outputPath в project.json — ../public, то есть /app/public
RUN corepack yarn build

# Непривилегированный nginx: работает не от root, слушает 8080
FROM nginxinc/nginx-unprivileged:alpine
COPY docker/prod/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/public /var/www/public
