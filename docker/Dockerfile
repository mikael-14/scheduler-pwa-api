FROM php:8.2-fpm

ARG USER
ARG UID

RUN apt-get update && apt-get install -y \
    git curl unzip zip \
    libpng-dev libonig-dev libxml2-dev \
    libicu-dev libzip-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo_mysql mbstring exif pcntl bcmath gd intl zip opcache \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# OPcache (DEV friendly)
RUN echo "opcache.enable=1\n\
opcache.validate_timestamps=1\n\
opcache.revalidate_freq=1\n\
opcache.max_accelerated_files=20000\n\
opcache.memory_consumption=128\n\
opcache.interned_strings_buffer=16" \
> /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN useradd -G www-data,root -u $UID -d /home/$USER $USER \
    && mkdir -p /home/$USER/.composer \
    && chown -R $USER:$USER /home/$USER

WORKDIR /var/www
USER $USER
