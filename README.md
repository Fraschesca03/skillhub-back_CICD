# SkillHub Backend

API REST du projet SkillHub — plateforme de mise en relation entre formateurs et apprenants.

Bloc 03 — Cloud, DevOps et Architecture | Bachelor CDWFS 2025/2026

---

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Langage | PHP 8.2 |
| Framework | Laravel 11 |
| Auth | JWT (tymon/jwt-auth) |
| Base de donnees principale | MySQL 8 |
| Base de donnees secondaire | MongoDB 6 |
| Tests | PHPUnit (Laravel Test) |
| Lint | PHP_CodeSniffer PSR-12 |
| Qualite | SonarCloud |
| Conteneurisation | Docker (multi-stage) |
| CI/CD | GitHub Actions |

---

## Prerequis

- Docker Desktop >= 24
- Git >= 2.40
- PHP 8.2 (pour developpement local sans Docker)
- Composer >= 2
- Compte SonarCloud (pour les scans qualite)
- Compte Docker Hub (pour le push d images)

---

## Installation locale

### 1. Cloner le depot

```bash
git clone https://github.com/poun-2108/skillhub-back_CICD.git
cd skillhub-back_CICD
```

### 2. Configurer l environnement

```bash
cp .env.example .env
```

Editer `.env` avec vos valeurs locales (base de donnees, MongoDB, clef JWT).

### 3. Installer les dependances

```bash
composer install
```

### 4. Generer la clef applicative et la clef JWT

```bash
php artisan key:generate
php artisan jwt:secret
```

### 5. Lancer les migrations

```bash
php artisan migrate
```

### 6. Demarrer le serveur de developpement

```bash
php artisan serve
```

L API est disponible sur `http://localhost:8000/api`.

---

## Lancer avec Docker

### Demarrage de la stack complete

```bash
docker compose up --build
```

La stack demarre : API (PHP-FPM) + MySQL + MongoDB.

### Arreter la stack

```bash
docker compose down
```

### Arreter et supprimer les volumes (reset total)

```bash
docker compose down -v
```

---

## Executer les tests

### Tests simples (sans couverture)

```bash
php artisan test
```

### Tests avec rapport de couverture (format Clover pour SonarCloud)

```bash
mkdir -p build/logs
php artisan test \
  --coverage-clover=build/logs/clover.xml \
  --log-junit=build/logs/junit.xml
```

### Lancer le lint PHP (PSR-12)

```bash
# Installer phpcs globalement si pas present
composer global require squizlabs/php_codesniffer

# Lancer le lint sur app/
~/.composer/vendor/bin/phpcs --standard=PSR12 --extensions=php app/
```

---

## Variables d environnement

Toutes les variables requises sont documentees dans `.env.example`.

| Variable | Description |
|----------|-------------|
| `APP_KEY` | Clef de chiffrement Laravel |
| `DB_CONNECTION` | Pilote BDD (mysql) |
| `DB_HOST` | Hote MySQL |
| `DB_DATABASE` | Nom de la base MySQL |
| `DB_USERNAME` | Utilisateur MySQL |
| `DB_PASSWORD` | Mot de passe MySQL |
| `MONGO_HOST` | Hote MongoDB |
| `MONGO_DATABASE` | Base MongoDB |
| `JWT_SECRET` | Clef secrete JWT |
| `MAIL_MAILER` | Transport email (log en dev) |

> **Important :** Le fichier `.env` ne doit jamais etre commite. Seul `.env.example` est versionne.

---

## Pipeline CI/CD

### Declenchement automatique

| Evenement | Jobs executes |
|-----------|---------------|
| Push sur `dev` | install, lint, test, build |
| Push sur `main` | install, lint, test, build, push |
| Pull Request vers `dev` ou `main` | install, lint, test, build |

### Etapes du pipeline CI

```
install  -->  lint  -->  test + SonarCloud  -->  build image Docker
                                                        |
                                              (merge main seulement)
                                                        |
                                                 push Docker Hub
```

### Secrets GitHub Actions requis

Configurer dans `Settings > Secrets and variables > Actions` du depot :

| Secret | Description |
|--------|-------------|
| `DOCKER_USERNAME` | Identifiant Docker Hub |
| `DOCKER_PASSWORD` | Mot de passe Docker Hub |
| `SONAR_TOKEN` | Token SonarCloud |
| `APP_KEY` | Clef Laravel pour les tests CI |

### Tag de l image Docker

Chaque image est taguee avec le git SHA du commit :

```
docker.io/<DOCKER_USERNAME>/skillhub-back:<git-sha>
docker.io/<DOCKER_USERNAME>/skillhub-back:latest
```

---

## Structure du depot

```
skillhub-back_CICD/
├── .github/
│   └── workflows/
│       └── ci.yml              # Pipeline CI/CD GitHub Actions
├── app/
│   ├── Http/
│   │   ├── Controllers/        # AuthController, FormationController, etc.
│   │   └── Middleware/         # CorsMiddleware
│   ├── Mail/                   # NouveauMessageMail
│   ├── Models/                 # User, Formation, Module, Inscription...
│   └── Services/               # ActivityLogService
├── config/                     # Configuration Laravel
├── database/
│   └── migrations/             # Migrations MySQL
├── routes/
│   └── api.php                 # Routes API REST
├── tests/
│   └── Feature/
│       └── SkillHubTest.php    # Tests fonctionnels (34 tests)
├── .dockerignore               # Exclusions contexte Docker
├── .env.example                # Template variables d environnement
├── .gitignore                  # Exclusions Git
├── Dockerfile                  # Image multi-stage PHP 8.2
├── docker-compose.yml          # Orchestration locale
├── sonar-project.properties    # Configuration SonarCloud
├── CONTRIBUTING.md             # Guide de contribution
└── README.md                   # Ce fichier
```

---

## Strategie de branches

| Branche | Role |
|---------|------|
| `main` | Production — code stable, aucun commit direct |
| `dev` | Integration — accumule les features validees |
| `feature/<nom>` | Une branche par fonctionnalite |
| `hotfix/<nom>` | Correction urgente — merge vers main et dev |

Voir `CONTRIBUTING.md` pour la procedure complete.

---

## Endpoints API principaux

| Methode | Route | Description |
|---------|-------|-------------|
| POST | `/api/register` | Inscription utilisateur |
| POST | `/api/login` | Connexion + token JWT |
| GET | `/api/profile` | Profil utilisateur connecte |
| POST | `/api/logout` | Deconnexion |
| GET | `/api/formations` | Liste des formations |
| POST | `/api/formations` | Creer une formation (formateur) |
| POST | `/api/formations/{id}/inscription` | S inscrire (apprenant) |
| POST | `/api/modules/{id}/terminer` | Terminer un module |
| POST | `/api/messages/envoyer` | Envoyer un message |

---

## Qualite du code

- Analyse SonarCloud a chaque push
- Couverture de tests cible : 96%+
- Standard PSR-12 verifie a chaque CI
- Objectif : Quality Gate PASSED, moins de 10 issues

---

## Licence

Usage pedagogique interne — CDC-SKILLHUB-2026
