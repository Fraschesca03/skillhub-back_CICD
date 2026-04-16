# Stage 1 : builder
FROM php:8.2-cli-alpine AS builder

RUN apk add --no-cache \
    git curl zip unzip \
    libpng-dev oniguruma-dev libxml2-dev \
    autoconf g++ make

RUN docker-php-ext-install pdo pdo_mysql mbstring exif bcmath gd \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copier composer.json et composer.lock
COPY composer.json composer.lock ./

# --no-scripts pour eviter package:discover (artisan absent a cette etape)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

# Copier tout le code (artisan est maintenant disponible)
COPY . .

# Executer les scripts maintenant qu'artisan est present
RUN php artisan package:discover --ansi \
    && composer dump-autoload --optimize

# Stage 2 : runtime
FROM php:8.2-fpm-alpine AS runtime

RUN apk add --no-cache \
    libpng-dev oniguruma-dev libxml2-dev \
    autoconf g++ make \
    && docker-php-ext-install pdo pdo_mysql mbstring exif bcmath gd \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apk del autoconf g++ make

WORKDIR /var/www/html

COPY --from=builder /app /var/www/html

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
