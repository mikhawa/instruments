# 001 — Environnement Docker de développement

- **Modèle** : Opus
- **Justification** : mise en place de l'infrastructure de base du projet (architecture de l'environnement, sécurité de la configuration nginx/PHP, cible de build prod pour la CI).
- **Date** : 2026-09-28

## Fichiers créés

- `compose.yaml`
- `docker/php/Dockerfile` (cibles `base`, `dev`, `prod`)
- `docker/php/docker-entrypoint.sh`
- `docker/php/conf.d/app.ini`, `app.dev.ini`, `app.prod.ini`
- `docker/nginx/default.conf`
- `docker/mariadb/my.cnf`
- `.dockerignore`
- `Makefile`
- `docs/devops/docker-setup.md`

## Résumé

Stack dev : PHP 8.3 FPM (intl, pdo_mysql, zip, opcache, apcu, gd, xdebug), nginx, MariaDB 11.4 (utf8mb4),
phpMyAdmin, Mailpit. Installation automatique de Symfony 7.4 LTS `--webapp` au premier démarrage
(génération des fichiers Docker de Flex désactivée pour ne pas écraser la configuration MariaDB).
Utilisateur PHP aligné sur l'UID/GID de l'hôte. Migrations jouées automatiquement au démarrage.

## Vérification (2026-09-28)

`docker compose up -d --build` : 5 conteneurs démarrés, Symfony v7.4.19 installé automatiquement.
- http://localhost:8080 → page d'accueil Symfony (404 normal, aucune route)
- phpMyAdmin (8081) et Mailpit (8025) → HTTP 200
- Connexion Doctrine → MariaDB 11.4.10, utf8mb4, base `instruments`
- Fichiers générés appartenant à l'UID 1000, pas de `compose.override.yaml` Flex
- `.env` : `DATABASE_URL` (PostgreSQL par défaut) et `MAILER_DSN` alignés sur MariaDB / Mailpit
