# syntax=docker/dockerfile:1

# ---------------------------------------------------------------
# Base commune : PHP 8.3 FPM + extensions requises par Symfony 7.4
# ---------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS base

WORKDIR /var/www/app

# Installeur d'extensions PHP (gère les dépendances système)
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip acl \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions \
        intl \
        pdo_mysql \
        zip \
        opcache \
        apcu \
        gd

COPY docker/php/conf.d/app.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY --chmod=755 docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

ENV COMPOSER_ALLOW_SUPERUSER=1

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------
# Développement : Xdebug + utilisateur aligné sur l'hôte (UID/GID)
# ---------------------------------------------------------------
FROM base AS dev

ARG UID=1000
ARG GID=1000

ENV APP_ENV=dev

RUN install-php-extensions xdebug \
    && mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && groupadd -g ${GID} app \
    && useradd -u ${UID} -g app -m -s /bin/bash app \
    && mkdir -p /home/app/.composer/cache \
    && chown -R app:app /home/app /var/www/app

COPY docker/php/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/zz-app.dev.ini

ENV COMPOSER_HOME=/home/app/.composer

USER app

# ---------------------------------------------------------------
# Production / préprod (CI) : code embarqué, dépendances optimisées
# ---------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/zz-app.prod.ini

COPY composer.* symfony.* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && composer dump-env prod \
    && composer run-script --no-dev post-install-cmd \
    && chown -R www-data:www-data var
