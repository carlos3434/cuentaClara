# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — Frontend assets (Node only here; not shipped in the final image)
# ---------------------------------------------------------------------------
FROM node:22-bookworm-slim AS assets

WORKDIR /app

# Install JS deps first so this layer is cached when only PHP/source changes.
COPY package.json package-lock.json ./
RUN npm ci

# Vite needs the source it bundles (resources/, config) + the manifest output dir.
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build


# ---------------------------------------------------------------------------
# Stage 2 — PHP runtime
# ---------------------------------------------------------------------------
FROM php:8.3-cli-bookworm AS app

# System libs for the PHP extensions Laravel needs.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libonig-dev \
        libsqlite3-dev \
        libpq-dev \
        unzip \
        git \
        tesseract-ocr \
        tesseract-ocr-spa \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_sqlite \
        pdo_pgsql \
        mbstring \
        bcmath \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Composer binary from the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Production-friendly defaults. Render env vars override these at runtime.
# DB and receipts storage are intentionally NOT defaulted here: production
# must set DB_CONNECTION=pgsql (+ DB_* creds) and RECEIPTS_DISK=s3 (+ AWS_*)
# on Render so data and uploaded images survive deploys. The image still
# bundles pdo_sqlite/pdo_pgsql so either works.
ENV APP_ENV=production \
    APP_DEBUG=false \
    # Single web container: run queued jobs (AI receipt validation) inline.
    # Switch to "database" + a worker if you add a separate worker service.
    QUEUE_CONNECTION=sync \
    # This image ships Tesseract, so read real vouchers by default. Without
    # this the 'fake' reader would fabricate a matching amount in production.
    AI_DRIVER=ocr

# Install PHP deps first (cached unless composer.json/lock changes).
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

# App source.
COPY . .

# Built frontend assets from the Node stage.
COPY --from=assets /app/public/build ./public/build

# Finish Composer setup now that the full app is present.
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

# Writable dirs + non-root runtime user.
RUN chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache database

USER www-data

# Render routes traffic to $PORT (default 10000); the entrypoint binds it.
EXPOSE 10000

ENTRYPOINT ["/app/docker/entrypoint.sh"]
