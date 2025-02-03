FROM php:8.3-fpm AS base


ARG UID
ARG GID
ARG USER
ARG GROUP

ENV UID=${UID}
ENV GID=${GID}
ENV USER=${USER}
ENV GROUP=${GROUP}


WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    vim \
    libicu-dev \
    libpq-dev \
    zlib1g-dev \
    libpng-dev \
    libwebp-dev \ 
    libjpeg62-turbo-dev 

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql 
RUN docker-php-ext-configure gd --with-jpeg --with-webp
RUN docker-php-ext-install pdo pdo_pgsql pgsql gd opcache


RUN groupadd --force -g $GID $GROUP
RUN useradd -ms /bin/bash --no-user-group -g $GID -u $UID $USER
RUN usermod -u $UID $USER

COPY . .

RUN chown -R $USER:$GROUP ./

USER $USER

RUN composer install --optimize-autoloader --no-dev && mkdir -p storage/logs/app

FROM node:lts-alpine AS node_modules

RUN mkdir -p /app
WORKDIR /app
COPY . .

RUN npm install 
RUN npm run build

FROM base

COPY --from=node_modules /app/public/build /var/www/public/build