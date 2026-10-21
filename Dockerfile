# syntax=docker/dockerfile:1

FROM php:8.5.12-fpm-bookworm AS runtime

LABEL org.opencontainers.image.authors="Open SGF"

ENV DEBIAN_FRONTEND=noninteractive \
    LANG=C.UTF-8 \
    TZ=UTC

WORKDIR /var/www/html

COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        pdftk \
        supervisor \
    && install-php-extensions \
        bcmath \
        intl \
        mbstring \
        mysqli \
        pcntl \
        pdo_mysql \
        opentelemetry \
        redis \
        pdo_sqlite \
        zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

FROM runtime AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer install \
        --classmap-authoritative \
        --no-autoloader \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist

COPY . .

RUN composer dump-autoload \
        --classmap-authoritative \
        --no-dev \
        --no-interaction

FROM runtime AS production

COPY --chmod=755 docker/production/entrypoint /usr/local/bin/entrypoint
COPY docker/production/healthcheck /usr/local/bin/healthcheck
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/production/logging/ /etc/nginx/logging/
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html

RUN chmod +x /usr/local/bin/healthcheck \
    && chmod -R u=rwX,g=rX,o= storage bootstrap/cache \
    && chmod -R g+w storage bootstrap/cache

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD ["healthcheck"]

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
