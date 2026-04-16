# Guide de contribution — SkillHub Backend

## Repartition des roles

| Role | Membre | Responsabilites |
|------|--------|-----------------|
| Cloud Architect | Membre 1 | Rapport d audit cloud, comparaison fournisseurs, schema C4, plan budgetaire |
| DevOps Engineer | Membre 2 | Dockerfiles, docker-compose.yml, pipeline CI/CD GitHub Actions |
| Tech Lead | Membre 3 | Versionning Git, CONTRIBUTING.md, README.md, orchestration, securite |

---

## Strategie de branches

```
main        Production stable — aucun commit direct autorise
dev         Integration — branche de travail par defaut
feature/*   Une branche par fonctionnalite ou tache DevOps
hotfix/*    Correctifs urgents sur main (merge aussi sur dev)
```

### Creer une branche de fonctionnalite

```bash
git checkout dev
git pull origin dev
git checkout -b feature/nom-de-la-fonctionnalite
```

### Fusionner via Pull Request

1. Pousser la branche feature
2. Ouvrir une Pull Request vers `dev`
3. Decrire le travail effectue dans la PR
4. Attendre la validation du pipeline CI
5. Merger apres review

---

## Format des commits (Conventional Commits)

Tous les commits doivent suivre ce format :

```
<type>(<scope>): <description courte en anglais>
```

### Types acceptes

| Type | Usage |
|------|-------|
| `feat` | Nouvelle fonctionnalite |
| `fix` | Correction de bug |
| `docker` | Ajout ou modification Docker |
| `ci` | Modification du pipeline CI/CD |
| `docs` | Documentation uniquement |
| `chore` | Maintenance, mise a jour dependances |
| `test` | Ajout ou modification de tests |

### Exemples corrects

```bash
feat(api): add JWT authentication middleware
fix: resolve port conflict in docker-compose.yml
docker: add multi-stage Dockerfile for Laravel backend
ci: configure GitHub Actions with lint and test stages
docs: update README with docker compose up instructions
chore: update composer dependencies
test: add coverage for InscriptionController
```

### Exemples incorrects

```bash
# Trop vague
update files
fixed bug
wip

# Pas en Conventional Commits
Ajout du Dockerfile
```

---

## Procedure de Pull Request

1. **Titre** : suivre le format Conventional Commits (`feat(api): ...`)
2. **Description** : expliquer ce qui a ete fait et pourquoi
3. **Branch source** : toujours depuis `feature/*` ou `hotfix/*`
4. **Branch cible** : `dev` (jamais directement `main`)
5. **Pipeline CI** : la PR ne peut pas etre mergee si le pipeline echoue
6. **Review** : au moins un autre membre doit approuver

---

## Procedure de resolution de conflits

```bash
# 1. Mettre a jour dev en local
git checkout dev
git pull origin dev

# 2. Rebaser votre branche feature sur dev
git checkout feature/ma-fonctionnalite
git rebase dev

# 3. Resoudre les conflits fichier par fichier
# Puis marquer comme resolus
git add <fichier-resolu>
git rebase --continue

# 4. Pousser la branche rebasee
git push origin feature/ma-fonctionnalite --force-with-lease
```

---

## Regles de securite

- Ne jamais commiter de fichier `.env`
- Ne jamais mettre de credentials en clair dans les Dockerfiles ou le pipeline
- Toutes les valeurs sensibles sont dans les GitHub Actions Secrets
- Images Docker taguees avec le git SHA, pas `latest` uniquement

---

## Lancer les tests avant de pusher

```bash
# Tests rapides
php artisan test

# Tests avec couverture
mkdir -p build/logs
php artisan test --coverage-clover=build/logs/clover.xml

# Lint PSR-12
~/.composer/vendor/bin/phpcs --standard=PSR12 --extensions=php app/
```
