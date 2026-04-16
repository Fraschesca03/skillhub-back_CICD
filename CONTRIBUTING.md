# CONTRIBUTING.md — SkillHub Backend

Guide de contribution pour le projet SkillHub (Bloc 03 — Cloud, DevOps et Architecture).

---

## Répartition des rôles

| Membre | Rôle | Responsabilités principales |
|--------|------|-----------------------------|
| Theo   | Tech Lead | Versionning Git, README, CONTRIBUTING, coordination, SonarCloud |
| Fitia  | Cloud Architect | Rapport d'audit, schéma C4, plan budgétaire, comparaison cloud |
| Nirina | DevOps Engineer | Dockerfile, docker-compose.yml, pipeline CI/CD, orchestration |

---

## Stratégie de branches

```
main    ← Production : code stable uniquement, aucun commit direct
 │
dev     ← Intégration : accumule les fonctionnalités validées
 │
 ├── feature/nom-feature    ← Développement d'une fonctionnalité
 ├── feature/docker-api     ← Exemple : ajout Dockerfile API
 ├── feature/ci-pipeline    ← Exemple : configuration GitHub Actions
 └── hotfix/nom-fix         ← Correctifs urgents → merge vers main ET dev
```

### Règles obligatoires

- Jamais de commit direct sur `main`
  - Tout développement passe par une branche `feature/<nirina/theo/fitia>-descriptif`
- Les Pull Requests doivent mentionner l'auteur et décrire le travail
- Un reviewer minimum avant de merger sur `dev`
- `main` ne reçoit que des merges depuis `dev` (ou `hotfix/`)

---

## Format des commits (Conventional Commits)

```
<type>(<scope>): <description courte>

[corps optionnel]

[footer optionnel]
```

### Types autorisés

| Type | Usage | Exemple |
|------|-------|---------|
| `feat` | Nouvelle fonctionnalité | `feat(api): add JWT authentication middleware` |
| `fix` | Correction de bug | `fix: resolve port conflict in docker-compose.yml` |
| `docker` | Fichiers de conteneurisation | `docker: add multi-stage Dockerfile for API` |
| `ci` | Pipeline CI/CD | `ci: configure GitHub Actions with lint and test stages` |
| `docs` | Documentation | `docs: update README with docker compose up instructions` |
| `test` | Ajout ou correction de tests | `test: add CoverageTest for FormationController` |
| `chore` | Maintenance | `chore: update composer dependencies` |
| `refactor` | Refactoring sans changement fonctionnel | `refactor: extract JWT helper to base controller` |

### Exemples concrets du projet

```bash
feat(auth): add profile photo upload endpoint
fix(cors): externalize allowed origin to APP_CORS_ORIGIN env variable
docker: add nginx reverse proxy service to docker-compose.yml
ci: pin ext-mongodb to 2.2.0 to match composer.lock
test: add MessageTest to cover MessageController endpoints
docs: add C4 architecture diagrams to audit report
```

---

## Procédure de Pull Request

1. Créer une branche depuis `dev` :
   ```bash
   git checkout dev
   git pull origin dev
   git checkout -b feature/nom-descriptif
   ```

2. Développer et commiter avec Conventional Commits

3. Pousser la branche :
   ```bash
   git push origin feature/nom-descriptif
   ```

4. Ouvrir une Pull Request sur GitHub :
    - **Titre** : `feat(scope): description` (même format que les commits)
    - **Description** : ce qui a été fait, pourquoi, comment tester
    - **Assignee** : l'auteur
    - **Reviewer** : un autre membre de l'équipe
    - **Base branch** : `dev` (jamais `main` directement)

5. Le reviewer approuve ou demande des modifications

6. Merger uniquement après approbation et pipeline CI vert

---

## Procédure de résolution de conflits

```bash
# 1. Mettre à jour dev localement
git checkout dev
git pull origin dev

# 2. Rebaser la branche feature sur dev
git checkout feature/ma-branche
git rebase dev

# 3. Résoudre les conflits fichier par fichier
# Éditer les fichiers conflictuels, puis :
git add fichier-conflit.php
git rebase --continue

# 4. Forcer le push de la branche rebasée
git push --force-with-lease origin feature/ma-branche
```

En cas de conflit complexe, contacter le Tech Lead avant de forcer un merge.

---

## Lancer les tests localement

```bash
# Tests complets avec couverture
php artisan test --coverage-clover=build/logs/clover.xml

# Tests d'un fichier spécifique
php artisan test --filter=SkillHubTest
php artisan test --filter=MessageTest
php artisan test --filter=CoverageTest

# Lint PHP (syntaxe)
find app routes -name "*.php" | xargs php -l
```

---

## Lancer l'application avec Docker

```bash
# Démarrage complet (API + MySQL + MongoDB)
docker compose up --build

# En arrière-plan
docker compose up -d --build

# Voir les logs
docker compose logs -f api

# Arrêter
docker compose down
```

---

## Variables d'environnement requises

Copier `.env.example` en `.env` et renseigner toutes les valeurs.
Ne jamais commiter `.env` (protégé par `.gitignore`).

---

## Quality Gate SonarCloud

Le pipeline bloque si :
- Couverture de code sur le nouveau code < 80 %
- Issues critiques introduites > 0

Vérifier localement avant de pousser :
```bash
php artisan test --coverage-clover=build/logs/clover.xml --log-junit=build/logs/junit.xml
```
