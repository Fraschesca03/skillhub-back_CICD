#!/bin/sh
# ─── docker/entrypoint.sh — Démarrage du container API Laravel ───────────────
# Ce script s'exécute avant PHP-FPM à chaque démarrage du container.
# Il effectue les opérations Laravel nécessaires au démarrage.
# ─────────────────────────────────────────────────────────────────────────────

set -e

echo "=== SkillHub API — Démarrage ==="

# Vérification que APP_KEY est défini
if [ -z "$APP_KEY" ]; then
    echo "ERREUR : APP_KEY non définie. Générer avec : php artisan key:generate"
    exit 1
fi

# Vérification que JWT_SECRET est défini
if [ -z "$JWT_SECRET" ]; then
    echo "ERREUR : JWT_SECRET non défini. Générer avec : php artisan jwt:secret"
    exit 1
fi

# Attente que MySQL soit disponible
echo "Attente de MySQL..."
until php -r "new PDO('mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-skillhub}', '${DB_USERNAME:-skillhub}', '${DB_PASSWORD}');" 2>/dev/null; do
    echo "MySQL non disponible — nouvelle tentative dans 3s..."
    sleep 3
done
echo "MySQL disponible."

# Exécution des migrations Laravel
echo "Exécution des migrations..."
php artisan migrate --force --no-interaction

# Nettoyage et mise en cache de la configuration (optimisation production)
echo "Optimisation Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== Démarrage PHP-FPM ==="

# Démarrage de PHP-FPM (commande passée en argument)
exec "$@"
