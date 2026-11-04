# Illuminate — Bi-Tech Laravel Zero CLI challenge
# PHP 8.4: the shipped composer.lock pins Symfony 8 packages that require >= 8.4
FROM php:8.4-cli-alpine

ARG HOST_UID=1000
ARG HOST_GID=1000

# System deps + PHP extensions
#  - zip/unzip/git : composer dist installs
#  - intl, bcmath  : common Laravel needs
#  - pcntl, posix  : Laravel Zero signals + Pest --parallel
#  - pcov          : fast code coverage (pest --coverage)
#  - xdebug        : installed, controlled by XDEBUG_MODE (off by default)
RUN apk add --no-cache git unzip bash libzip-dev icu-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && docker-php-ext-install -j"$(nproc)" zip intl bcmath pcntl posix pdo_mysql \
    && pecl install pcov xdebug \
    && docker-php-ext-enable pcov xdebug \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Non-root user matching the host UID/GID -> no root-owned files in your project
RUN addgroup -g "${HOST_GID}" app \
    && adduser -D -u "${HOST_UID}" -G app -s /bin/bash app \
    && mkdir -p /app /home/app/.composer/cache \
    && chown -R app:app /app /home/app

ENV COMPOSER_HOME=/home/app/.composer \
    COMPOSER_MEMORY_LIMIT=-1 \
    XDEBUG_MODE=off \
    PATH="/app/vendor/bin:${PATH}"

WORKDIR /app
USER app

CMD ["sleep", "infinity"]
