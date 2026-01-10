FROM php:8.2-cli

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libonig-dev \ \
    && docker-php-ext-install pdo pdo_mysql intl mbstring zip

# Устанавливаем composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY . /app

RUN composer install --no-progress --no-interaction --prefer-dist

CMD ["vendor/bin/phpunit", "--coverage-html", "build/coverage-html"]
