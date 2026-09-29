# Déploiement sur le VPS (Plesk, nginx + Apache)

Préprod et production tournent **sans Docker** sur le VPS Debian 12 administré par Plesk.
GitHub Actions construit un paquet complet (dépendances PHP, assets, build React), l'envoie
par SSH, puis le script `deploy/activer-release.sh` l'active sur le serveur.

```
pull request   → tests (Symfony + React)
push sur main  → tests → paquet → préprod
tag v*         → tests → paquet → production (validation manuelle)
```

Le serveur n'a besoin ni de Node ni de Composer : seulement PHP 8.3 et MariaDB.

---

## 1. Architecture sur le serveur

Un seul domaine sert à la fois React et Symfony. Il n'y a donc ni CORS ni second cookie :
la session est commune au site et au back-office.

```
/var/www/vhosts/<domaine>/instruments/      ← DEPLOY_PATH
├── current -> releases/20260929-153000-a1b2c3d   ← racine du document : current/public
├── releases/                                ← 5 dernières versions
│   └── 20260929-153000-a1b2c3d/
│       ├── public/index.html, app/          ← build React
│       ├── public/index.php, bundles/, assets/  ← Symfony
│       ├── public/.htaccess                 ← routage (voir ci-dessous)
│       ├── .env.local -> ../../shared/.env.local
│       ├── public/uploads -> ../../shared/public/uploads
│       └── var/log -> ../../shared/var/log
└── shared/
    ├── .env.local                           ← secrets (jamais dans git)
    ├── public/uploads/                      ← images envoyées
    ├── var/log/                             ← journaux Symfony
    └── sauvegardes/                         ← dump SQL avant chaque migration (10 derniers)
```

Routage (`public/.htaccess`, Apache) :

| URL | Servie par |
|---|---|
| fichier existant (`/app/…`, `/assets/…`, `/bundles/…`, `/uploads/…`) | Apache, directement |
| `/api/…`, `/admin/…` | Symfony (`index.php`) |
| fichier statique absent (`/app/x.js`) | 404 |
| toute autre URL (`/`, `/instruments/oud-turc`, `/connexion`) | `index.html` (React Router) |

Le build Vite range ses fichiers dans `app/` (`frontend/vite.config.js`), car `/assets/` est
réservé à l'AssetMapper de Symfony.

---

## 2. Préparation du serveur (une fois par environnement)

À faire pour la **préprod** (ex. `preprod.<domaine>`) puis pour la **production**.

### 2.1 Plesk

1. **Sites Web & Domaines** → ajouter le domaine ou le sous-domaine.
2. **Hébergement & DNS → Paramètres d'hébergement** :
   - racine du document : `instruments/current/public` ;
   - (le dossier n'existe pas encore : Plesk le crée vide, le premier déploiement le remplacera
     par un lien, voir 2.3).
3. **PHP** : version **8.3**, gestionnaire **FPM servi par Apache**. Extensions requises :
   `intl`, `pdo_mysql`, `opcache`, `mbstring`, `zip`, `gd`, `apcu` (facultatif).
   Réglages conseillés : `memory_limit = 256M`, `upload_max_filesize = 20M`,
   `post_max_size = 25M`, `opcache.validate_timestamps = 0` en production.
4. **Apache & nginx** : laisser le mode par défaut (nginx en proxy devant Apache).
   Si « Servir les fichiers statiques directement par nginx » est coché, garder la liste
   d'extensions par défaut : les fichiers existants sont servis par nginx, tout le reste passe
   par Apache et `public/.htaccess`.
5. **SSL/TLS** : certificat Let's Encrypt, redirection HTTP → HTTPS. Indispensable : le cookie
   de session est `secure` en HTTPS.
6. **Bases de données** : créer une base (ex. `instruments_prod`) et un utilisateur dédié.
7. **Accès SSH** (Connexion Web & FTP ou Paramètres d'hébergement) : shell `/bin/bash`
   pour l'utilisateur système du domaine.

### 2.2 Clé SSH de déploiement

Sur votre poste, générer une clé **réservée à GitHub** (sans phrase de passe) :

```bash
ssh-keygen -t ed25519 -C "github-deploy-instruments" -f ~/.ssh/instruments_deploy -N ""
```

Ajouter `~/.ssh/instruments_deploy.pub` aux clés autorisées de l'utilisateur système du
domaine (`~/.ssh/authorized_keys` sur le serveur, ou Plesk → extension « SSH Keys Manager »).

Récupérer l'empreinte du serveur pour `SSH_KNOWN_HOSTS` :

```bash
ssh-keyscan -p <port> <hôte> 2>/dev/null
```

Vérifier l'empreinte affichée avec celle que Plesk ou l'hébergeur communique : c'est elle
qui protège le déploiement contre une usurpation du serveur.

### 2.3 Dossiers et secrets sur le serveur

Connecté en SSH avec l'utilisateur du domaine :

```bash
DEPLOY_PATH=/var/www/vhosts/<domaine>/instruments
mkdir -p $DEPLOY_PATH/{releases,shared/public/uploads,shared/var/log,shared/sauvegardes}
rmdir $DEPLOY_PATH/current/public $DEPLOY_PATH/current 2>/dev/null || true   # dossier vide créé par Plesk
nano $DEPLOY_PATH/shared/.env.local
chmod 600 $DEPLOY_PATH/shared/.env.local
```

Contenu de `shared/.env.local` :

```dotenv
APP_ENV=prod
APP_SECRET=<résultat de : openssl rand -hex 32>
DATABASE_URL="mysql://<utilisateur>:<mot_de_passe>@localhost:3306/<base>?serverVersion=11.4.0-MariaDB&charset=utf8mb4"
DEFAULT_URI=https://<domaine>
FRONT_URL=/
CORS_ALLOW_ORIGIN='^https://<domaine>$'
MAILER_DSN=smtp://<utilisateur>:<mot_de_passe>@<serveur_smtp>:587
```

Un `APP_SECRET` **différent** par environnement. Les caractères spéciaux du mot de passe
dans `DATABASE_URL` doivent être encodés (`@` → `%40`, `#` → `%23`, etc.).

### 2.4 GitHub

Liste détaillée et commandes : [`github-variables.md`](github-variables.md).

**Settings → Environments** : créer `preprod` et `production`.

| Nom | Type | Exemple |
|---|---|---|
| `SSH_HOST` | secret | `vps.mondomaine.be` |
| `SSH_PORT` | secret | `22` |
| `SSH_USER` | secret | utilisateur système du domaine |
| `SSH_PRIVATE_KEY` | secret | contenu de `~/.ssh/instruments_deploy` (clé privée) |
| `SSH_KNOWN_HOSTS` | secret | sortie de `ssh-keyscan` |
| `DEPLOY_PATH` | variable | `/var/www/vhosts/<domaine>/instruments` |
| `SITE_URL` | variable | `https://<domaine>` (sans `/` final) |
| `PHP_BIN` | variable (facultative) | défaut : `/opt/plesk/php/8.3/bin/php` |
| `RECHARGEMENT_PHP` | variable (facultative) | voir « OPcache » ci-dessous |

Pour `production` :
- **Required reviewers** : vous-même (le déploiement attend votre validation). Sur un dépôt
  **privé**, cette option demande un compte GitHub Pro / Team ; à défaut, le tag reste la
  seule action volontaire qui déclenche la production ;
- **Deployment branches and tags** : limiter aux tags `v*`.

Pour `preprod` : limiter à la branche `main`.

---

## 3. Déployer

**Préprod** : chaque push (ou merge de pull request) sur `main`.

**Production** :

```bash
git tag -a v1.0.0 -m "Première mise en ligne"
git push origin v1.0.0
```

puis valider le déploiement dans l'onglet **Actions** de GitHub.

Étapes exécutées sur le serveur par `deploy/activer-release.sh` :

1. vérifications (`shared/.env.local` présent, version de PHP) ;
2. liens vers `shared/` (`.env.local`, `public/uploads`, `var/log`) ;
3. **sauvegarde de la base** (`mysqldump`, 10 dernières conservées) ;
4. `doctrine:migrations:migrate` ;
5. `cache:warmup` (environnement `prod`) ;
6. **bascule atomique** du lien `current` ;
7. rechargement de PHP si `RECHARGEMENT_PHP` est défini ;
8. suppression des versions au-delà des 5 dernières.

Le workflow vérifie ensuite `/api/health` (connexion à la base comprise), `/` et `/admin/login`.
Si une étape échoue avant la bascule, **la version en ligne n'est pas touchée**.

---

## 4. Revenir en arrière

```bash
ssh <utilisateur>@<hôte>
cd /var/www/vhosts/<domaine>/instruments
bash current/deploy/revenir-version.sh "$PWD"
```

`current` repointe vers la version précédente ; la version fautive est renommée `*.echec`.

⚠️ Les **migrations ne sont pas annulées**. Si la version fautive a modifié le schéma :

```bash
gunzip < shared/sauvegardes/<base>_<date>.sql.gz | mysql <base>
```

---

## 5. Points d'attention

- **OPcache** : après la bascule, PHP peut servir l'ancienne version quelques secondes (cache
  realpath) — ou plus longtemps si `opcache.validate_timestamps = 0`. Solution : demander à
  l'administrateur du VPS une règle sudo limitée, puis définir la variable GitHub
  `RECHARGEMENT_PHP`, par exemple `sudo /usr/sbin/service plesk-php83-fpm reload`.
- **Fixtures** : ne **jamais** lancer `doctrine:fixtures:load` en préprod ou en production :
  la commande vide la base. Le bundle n'est d'ailleurs pas installé (`--no-dev`).
- **Premier compte administrateur** : à créer à la main après le premier déploiement.
  Hacher le mot de passe sur le serveur avec `php bin/console security:hash-password`
  (depuis `current/`), puis insérer l'utilisateur en SQL avec `roles = '["ROLE_ADMIN"]'`.
  Une commande dédiée (`app:creer-admin`) serait plus sûre : à prévoir.
- **Tests** : la CI lance PHPUnit, mais le projet n'a encore aucun test. Le garde-fou
  actuel se limite aux lints, aux migrations sur une base vierge et à `doctrine:schema:validate`.
- **Journaux** : `shared/var/log/prod.log`, conservés d'une version à l'autre.
