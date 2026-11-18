# syntax=docker/dockerfile:1
# One image for app, queue, and scheduler. Targets: dev (code bind-mounted) and prod (code baked in).
# PRD Arsitektur 7.2, decision A12.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=24

# ---------- base: runtime shared by every target ----------
FROM php:${PHP_VERSION}-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql bcmath gd intl zip opcache pcntl \
    && apk add --no-cache tzdata

# Run as the host user's UID/GID so files written to storage/ stay editable on the host.
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} app && adduser -D -u ${UID} -G app app

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

# ---------- dev: composer available, code comes from the bind mount ----------
FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer
COPY docker/php/php.dev.ini /usr/local/etc/php/conf.d/zz-dev.ini
USER app

# ---------- vendor: production PHP dependencies ----------
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------- assets: Vite build (Node only exists in this stage) ----------
FROM node:${NODE_VERSION}-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# ---------- prod ----------
FROM base AS prod
ARG GIT_COMMIT=unknown
ENV APP_ENV=production GIT_COMMIT=${GIT_COMMIT}
COPY --from=vendor --chown=app:app /var/www/html /var/www/html
COPY --from=assets --chown=app:app /app/public/build /var/www/html/public/build
RUN rm -rf tests node_modules .git docker/caddy \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache
USER app
HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 \
  CMD php-fpm -t >/dev/null 2>&1 || exit 1

# ---------- web (prod): Caddy with the same public/ as the app image ----------
FROM caddy:2-alpine AS web
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=prod /var/www/html/public /var/www/html/public
