FROM php:8.3-fpm-alpine

WORKDIR /var/www


RUN apk update && apk add postgresql-dev 
        
RUN docker-php-ext-install pdo pdo_pgsql  