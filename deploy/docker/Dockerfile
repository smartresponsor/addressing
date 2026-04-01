FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    chromium \
    chromium-driver \
    git \
    libicu-dev \
    libsqlite3-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-install intl pdo pdo_sqlite zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN git config --global --add safe.directory /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY deploy/docker/vhost.conf /etc/apache2/sites-available/000-default.conf

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PANTHER_CHROME_BINARY=/usr/bin/chromium \
    PANTHER_NO_SANDBOX=1

COPY . /var/www/html

RUN composer install --no-interaction --prefer-dist \
    && vendor/bin/bdi detect drivers
