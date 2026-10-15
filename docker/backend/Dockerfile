FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libonig-dev libpng-dev \
    && docker-php-ext-install pdo_mysql zip mbstring bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# 1) Laravel baru + Sanctum (sama seperti langkah manual di PANDUAN-INTEGRASI.md)
RUN composer create-project laravel/laravel:^12.0 . --no-interaction \
    && php artisan install:api --without-migration-prompt --no-interaction

# 2) Timpa dengan kode FoodStock (overlay)
COPY backend-overlay/ /var/www/html/
RUN rm -f database/migrations/*_create_personal_access_tokens_table.php

COPY docker/backend/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh && chmod -R 777 storage bootstrap/cache

EXPOSE 8000
ENTRYPOINT ["entrypoint.sh"]
