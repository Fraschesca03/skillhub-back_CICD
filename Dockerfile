# ─── Dockerfile — SkillHub API Laravel 12 ────────────────────────────────────
# Build multi-stage : builder (composer) + runtime (php-fpm)
# Image de base officielle alpine pour minimiser la taille
# Aucune credential en dur — toutes les variables via ARG/ENV
# ─────────────────────────────────────────────────────────────────────────────

# ─── Stage 1 : Builder — installation des dependances Composer ───────────────
FROM composer:2.7 AS builder

WORKDIR /app

# Copier uniquement les fichiers de dependances en premier
COPY composer.json composer.lock ./

# --no-scripts : evite que Composer cherche artisan avant qu il soit copie
# --ignore-platform-reqs : les extensions sont dans le stage runtime
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# Copier tout le code source (artisan inclus)
COPY . .

# Executer les scripts post-install maintenant qu artisan est disponible
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# ─── Stage 2 : Runtime — image legere pour la production ─────────────────────
FROM php:8.2-fpm-alpine AS runtime

LABEL maintainer="SkillHub Team"
LABEL version="1.0"
LABEL description="SkillHub API REST - Laravel 12 PHP 8.2"

# Installation des extensions PHP necessaires
RUN apk add --no-cache \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        opcache \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install mongodb-2.2.0 \
    && docker-php-ext-enable mongodb \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# Configuration Opcache pour les performances
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copier les fichiers depuis le stage builder (vendor inclus)
COPY --from=builder /app /var/www/html

# Repertoire pour les photos de profil
RUN mkdir -p /var/www/html/public/images/profils

# Permissions Laravel
RUN mkdir -p /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/public/images \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

EXPOSE 9000

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
