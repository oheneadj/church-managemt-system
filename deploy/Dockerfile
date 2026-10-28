# Production image for staging and production (docs/16-deployment-and-ops.md, decisions D-023 and D-029).
#
# One image, three roles. Coolify runs it as three applications, and each application sets CONTAINER_ROLE:
#   web        nginx + PHP-FPM serving the API and the React app (the only one with a domain)
#   worker     Supervisor running the queue workers
#   scheduler  php artisan schedule:work
#
# Build context is the repository root (Coolify: Base Directory "/", Dockerfile Location "/deploy/Dockerfile").
# This file has not been built on the development machine (no Docker there); the first build is on staging.

ARG PHP_TAG=8.4-fpm-nginx-v5.0.5
ARG NODE_TAG=24-alpine

# --- 1. The React app: Vite writes hashed files to public/build ------------------------------------------
FROM node:${NODE_TAG} AS assets
WORKDIR /build
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- 2. PHP dependencies and the optimised autoloader ----------------------------------------------------
# Same base image as the runtime, so PHP and its extensions match what the code will run on.
FROM serversideup/php:${PHP_TAG} AS vendor
WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY --chown=www-data:www-data . .
# This also runs the composer scripts: the AWS SDK is cut down to S3 and package discovery runs.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction

# --- 3. The runtime image ---------------------------------------------------------------------------------
FROM serversideup/php:${PHP_TAG} AS runtime
# The base image runs as the named user www-data and its own docs use the name, so USER is not numeric.
# hadolint ignore=DL3066
USER root

# bcmath (money arithmetic) and intl match CI and local development. Supervisor runs the queue workers.
# Supervisor comes from the Debian release repo; pinning an apt version would break the build whenever Debian
# replaces that package.
# hadolint ignore=DL3008
RUN install-php-extensions bcmath intl gd exif \
    && apt-get update \
    && apt-get install -y --no-install-recommends supervisor \
    && rm -rf /var/lib/apt/lists/*

COPY deploy/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY deploy/bin/start deploy/bin/healthcheck /usr/local/bin/
RUN chmod 0755 /usr/local/bin/start /usr/local/bin/healthcheck

COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /build/public/build /var/www/html/public/build
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# hadolint ignore=DL3066
USER www-data

# Safe defaults: no role set means web, OPcache on, and the image's own Laravel automations off because
# `start` runs the migrations, seeders and caches itself, in the right order, for the web role only.
ENV CONTAINER_ROLE=web \
    PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=false

# Replaces the base image's health check, which only knows about nginx. It checks the right thing per role.
HEALTHCHECK --interval=15s --timeout=5s --start-period=120s --retries=3 CMD ["/usr/local/bin/healthcheck"]

# The base image's ENTRYPOINT still runs first, then this.
CMD ["/usr/local/bin/start"]
