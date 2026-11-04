FROM php:8.2-fpm-alpine

WORKDIR /var/www/html/

RUN apk add --no-cache curl git zip librdkafka-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka \
    && docker-php-ext-install pdo_mysql \
    && apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

EXPOSE 9000