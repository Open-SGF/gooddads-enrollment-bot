# syntax=docker/dockerfile:1

FROM ubuntu:24.04 AS runtime

LABEL org.opencontainers.image.authors="Open SGF"

ENV DEBIAN_FRONTEND=noninteractive \
    LANG=C.UTF-8 \
    TZ=UTC

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' \
        | gpg --dearmor -o /etc/apt/keyrings/ppa_ondrej_php.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" \
        > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        pdftk \
        php8.5-bcmath \
        php8.5-cli \
        php8.5-curl \
        php8.5-intl \
        php8.5-mbstring \
        php8.5-mysql \
        php8.5-redis \
        php8.5-sqlite3 \
        php8.5-xml \
        php8.5-zip \
        supervisor \
    && apt-get purge -y --auto-remove curl gnupg \
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

COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html

RUN chmod -R u=rwX,g=rX,o= storage bootstrap/cache \
    && chmod -R g+w storage bootstrap/cache

USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD ["supervisorctl", "-c", "/etc/supervisor/conf.d/supervisord.conf", "status"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
