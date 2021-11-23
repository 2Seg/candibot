FROM php:7.3-apache

ENV workdir /var/www/candibot

COPY . ${workdir}
WORKDIR ${workdir}

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libzip-dev \
        libpng-dev

RUN docker-php-ext-install \
        zip \
        gd \
        bcmath

RUN composer install --no-interaction
