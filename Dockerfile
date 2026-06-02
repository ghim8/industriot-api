FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git curl zip unzip supervisor \
    libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install \
       pdo pdo_mysql mbstring zip exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader
RUN chmod -R 777 storage bootstrap/cache

EXPOSE 8000

CMD php artisan config:clear && \
    php artisan cache:clear --no-interaction || true && \
    php artisan migrate --force && \
    /usr/bin/supervisord -n -c /var/www/supervisord.conf