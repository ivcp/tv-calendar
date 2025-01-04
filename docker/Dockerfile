FROM php:8.3-fpm


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
    jq

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql 
RUN docker-php-ext-install pdo pdo_pgsql pgsql 

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug 

COPY xdebug.ini "${PHP_INI_DIR}/conf.d"


RUN groupadd --force -g $GID $GROUP
RUN useradd -ms /bin/bash --no-user-group -g $GID -u $UID $USER
RUN usermod -u $UID $USER

RUN chmod -R 770 ./

USER $USER


