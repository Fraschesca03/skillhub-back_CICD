# ─── Dockerfile — SkillHub API Laravel 12 ────────────────────────────────────
# Build multi-stage : builder (composer) + runtime (php-fpm)
# Image de base officielle alpine pour minimiser la taille
# Aucune credential en dur — toutes les variables via ARG/ENV
# ─────────────────────────────────────────────────────────────────────────────

# ─── Stage 1 : Builder — installation des dépendances Composer ───────────────
FROM composer:2.7 AS builder

WORKDIR /app

# Copier uniquement les fichiers de dépendances en premier
# (optimisation du cache Docker : rebuild seulement si composer.json change)
COPY composer.json composer.lock ./

# Installation des dépendances sans les packages de dev
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# Copier le reste du code source
COPY . .

# ─── Stage 2 : Runtime — image légère pour la production ─────────────────────
FROM php:8.2-fpm-alpine AS runtime

# Métadonnées de l'image
LABEL maintainer="SkillHub Team"
LABEL version="1.0"
LABEL description="SkillHub API REST - Laravel 12 PHP 8.2"

# Installation des extensions PHP nécessaires
RUN apk add --no-cache \
    # Bibliothèques système
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    # Extensions PHP
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        opcache \
    # Extension MongoDB via PECL
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install mongodb-2.2.0 \
    && docker-php-ext-enable mongodb \
    && apk del .build-deps \
    # Nettoyage du cache
    && rm -rf /tmp/* /var/cache/apk/*

# Configuration Opcache pour optimiser les performances Laravel
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copier les fichiers depuis le stage builder (vendor inclus)
COPY --from=builder /app /var/www/html

# Création du répertoire pour les photos de profil
RUN mkdir -p /var/www/html/public/images/profils

# Permissions Laravel : storage et bootstrap/cache accessibles par www-data
RUN chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/public/images \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Port exposé par PHP-FPM
EXPOSE 9000

# Variables d'environnement avec valeurs par défaut non sensibles
# Les valeurs sensibles (DB_PASSWORD, JWT_SECRET...) sont injectées au runtime
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Script de démarrage : migrations + démarrage PHP-FPM
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
