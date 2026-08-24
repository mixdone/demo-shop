FROM php:8.2-fpm-alpine

WORKDIR /var/www/html/

RUN apk add --no-cache curl git zip

RUN docker-php-ext-install pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
 
RUN composer require aws/aws-sdk-php -n --no-scripts

EXPOSE 9000
