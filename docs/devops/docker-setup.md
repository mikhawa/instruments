# Environnement Docker (dev)

## Services

| Service      | Image                       | Accès hôte                     | Rôle                                   |
|--------------|-----------------------------|--------------------------------|----------------------------------------|
| `php`        | build `docker/php/Dockerfile` (cible `dev`) | —              | PHP 8.3 FPM, Composer, Xdebug          |
| `nginx`      | `nginx:1.27-alpine`         | http://localhost:8080          | Serveur web (`public/`)                |
| `database`   | `mariadb:11.4`              | `localhost:3307`               | Base de données                        |
| `phpmyadmin` | `phpmyadmin:5`              | http://localhost:8081          | Administration BDD                     |
| `mailer`     | `axllent/mailpit`           | http://localhost:8025          | Capture des e-mails sortants           |
| `node`       | `node:24-alpine`            | http://localhost:5173          | Front React (serveur de dev Vite)      |

Identifiants BDD par défaut : `app` / `app`, base `instruments` (root : `root`).

## Démarrage

```bash
make up        # construit les images et démarre les conteneurs
make help      # liste des commandes disponibles
```

`make` n'est pas installé par défaut sous WSL/Debian : `sudo apt install make`,
ou utiliser directement `docker compose up -d --build`.

Au **premier lancement**, si `composer.json` est absent, le conteneur `php` installe
automatiquement Symfony 7.4 LTS (`symfony/skeleton` + `webapp`) dans le projet.
Suivre la progression avec `make logs s=php`.

Ensuite, à chaque démarrage, l'entrypoint :
1. lance `composer install` si `vendor/` est vide ;
2. joue les migrations Doctrine en attente.

## Commandes utiles

```bash
make sh                                  # shell dans le conteneur PHP
make console c="make:entity Instrument"  # console Symfony
make composer c="require api"
make db-reset                            # recrée la base + migrations
make test                                # PHPUnit
```

## Front-end React (Vite)

L'application React se trouve dans `frontend/` (React 19, Vite, JavaScript/JSX).

- En dev, ouvrir **http://localhost:5173** : rechargement à chaud (HMR) à chaque modification.
- Les appels à `/api/*` sont relayés par le proxy Vite vers Symfony (nginx) : même origine, pas de CORS.
  Dans le code React, appeler simplement `fetch('/api/...')`.
- Point de contrôle : `GET /api/health` → `{"status":"ok","database":"ok"}`.
- `node_modules` est installé automatiquement au démarrage du conteneur s'il est absent.

```bash
make npm c="install react-router"   # ajouter une dépendance
make front-build                    # build de production → frontend/dist
```

En production, `frontend/dist` est un site statique à servir par le serveur web, les requêtes
`/api` étant routées vers Symfony (configuration Plesk à définir dans `docs/devops/vps-preprod.md`).

## Variables surchargeables

À définir dans l'environnement du shell ou dans `.env` (lu par Docker Compose) :

`HTTP_PORT`, `DB_PORT`, `PMA_PORT`, `MAILPIT_PORT`, `MARIADB_DATABASE`, `MARIADB_USER`,
`MARIADB_PASSWORD`, `MARIADB_ROOT_PASSWORD`, `XDEBUG_MODE`, `VITE_PORT`.

`DATABASE_URL` et `MAILER_DSN` sont injectées par `compose.yaml` et priment sur le `.env` de Symfony.

## Cache Symfony périmé

Après une modification de mapping (entités, groupes de sérialisation), si l'API renvoie encore
l'ancien format, `cache:clear` peut ne pas suffire (pools de métadonnées + APCu de PHP-FPM) :

```bash
rm -rf var/cache/dev && docker compose restart php
```

## Xdebug

Désactivé par défaut. Pour l'activer :

```bash
XDEBUG_MODE=debug make up
```

PhpStorm : créer un serveur nommé `instruments` avec le mapping `<projet>` → `/var/www/app`, port 9003.

## Permissions

L'utilisateur du conteneur PHP reprend l'UID/GID de l'hôte (passés par le `Makefile`),
les fichiers générés (`make:entity`, `var/`…) appartiennent donc à l'utilisateur local.

## Cible `prod`

`docker/php/Dockerfile` contient une cible `prod` (code embarqué, `composer install --no-dev`,
OPcache preload) utilisable en CI/préprod :

```bash
docker build -f docker/php/Dockerfile --target prod -t instruments-php:prod .
```

La production (VPS Debian sous Plesk) n'utilise pas Docker : voir `docs/devops/vps-preprod.md`.
