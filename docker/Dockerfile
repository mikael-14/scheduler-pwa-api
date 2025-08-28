FROM php:8.2-fpm

# Arguments defined in docker-compose.yml
ARG user
ARG uid
ARG app_debug

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libicu-dev \
    libzip-dev

RUN docker-php-ext-configure intl \
&& docker-php-ext-install intl


RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y build-essential nodejs yarn

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip


# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


# Enable xdebug if APP_DEBUG is true on .env
RUN if [ $app_debug = "true" ] ; then \
    pecl install xdebug \
    && docker-php-ext-enable xdebug; \
fi ;

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www

USER $user
