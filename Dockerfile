# ============================================================
# SkillHub Backend - Dockerfile multi-stage
# Stage 1 : builder  - installe les dependances
# Stage 2 : runtime  - image legere de production
# ============================================================

# -----------------------------------------------------------
# STAGE 1 : builder
# -----------------------------------------------------------
FROM php:8.2-cli-alpine AS builder

# Dependances systeme necessaires pour compiler les extensions
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    autoconf \
    g++ \
    make

# Extensions PHP necessaires pour Laravel + MongoDB
RUN docker-php-ext-install pdo pdo_mysql mbstring exif bcmath gd \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# Composer depuis l'image officielle
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# On copie uniquement les fichiers de dependances d'abord
# pour profiter du cache Docker si composer.json ne change pas
COPY composer.json composer.lock ./

# Installation des dependances sans les packages de dev
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# On copie ensuite tout le code source
COPY . .

# Optimisation de l'autoloader pour la production
RUN composer dump-autoload --optimize

# -----------------------------------------------------------
# STAGE 2 : runtime - image finale legere
# -----------------------------------------------------------
FROM php:8.2-fpm-alpine AS runtime

# Dependances runtime uniquement
RUN apk add --no-cache \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    autoconf \
    g++ \
    make \
    && docker-php-ext-install pdo pdo_mysql mbstring exif bcmath gd \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apk del autoconf g++ make

WORKDIR /var/www/html

# On recupere l'application depuis le stage builder
COPY --from=builder /app /var/www/html

# Droits corrects pour Laravel sur storage et cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Port PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
