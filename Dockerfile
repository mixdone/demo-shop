FROM php:8.2-fpm-alpine

WORKDIR /var/www/html/

RUN apk add --no-cache curl git zip

RUN docker-php-ext-install pdo_mysql

EXPOSE 9000
