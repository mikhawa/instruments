# Déploiement sur le VPS (CyberPanel, OpenLiteSpeed)

Préprod et production tournent **sans Docker** sur le VPS administré par CyberPanel
(serveur web OpenLiteSpeed, PHP 8.3 `lsphp83`, MariaDB). GitHub Actions construit un paquet
complet (dépendances PHP, assets, build React), l'envoie par SSH, puis le script
`deploy/activer-release.sh` l'active sur le serveur.

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

CyberPanel crée pour chaque site un utilisateur système dont le dossier personnel est
`/home/<domaine>`, et sert le dossier `/home/<domaine>/public_html`. Ce dossier est remplacé
par un **lien** vers la version en ligne :

```
/home/<domaine>/
├── public_html -> instruments/current/public        ← racine servie par OpenLiteSpeed
└── instruments/                                     ← DEPLOY_PATH
    ├── current -> releases/20261017-153000-a1b2c3d
    ├── releases/                                    ← 5 dernières versions
    │   └── 20261017-153000-a1b2c3d/
    │       ├── public/index.html, app/              ← build React
    │       ├── public/index.php, bundles/, assets/  ← Symfony
    │       ├── public/.htaccess                     ← routage (voir ci-dessous)
    │       ├── .env.local -> ../../shared/.env.local
    │       ├── public/uploads -> ../../shared/public/uploads
    │       └── var/log -> ../../shared/var/log
    └── shared/
        ├── .env.local                               ← secrets (jamais dans git)
        ├── public/uploads/                          ← images envoyées
        ├── var/log/                                 ← journaux Symfony
        └── sauvegardes/                             ← dump SQL avant chaque migration (10 derniers)
```

Garder `public_html` (sous forme de lien) plutôt que modifier la racine du site dans
CyberPanel : le renouvellement automatique du certificat Let's Encrypt écrit dans
`public_html/.well-known/`, et continue ainsi de fonctionner.

Routage (`public/.htaccess`, lu par OpenLiteSpeed) :

| URL | Servie par |
|---|---|
| fichier existant (`/app/…`, `/assets/…`, `/bundles/…`, `/uploads/…`, `/.well-known/…`) | OpenLiteSpeed, directement |
| `/api/…`, `/admin/…` | Symfony (`index.php`) |
| fichier statique absent (`/app/x.js`) | 404 |
| toute autre URL (`/`, `/instruments/oud-turc`, `/connexion`) | `index.html` (React Router) |

Le build Vite range ses fichiers dans `app/` (`frontend/vite.config.js`), car `/assets/` est
réservé à l'AssetMapper de Symfony.

### Communication React ↔ API Symfony

En production, **React n'est plus un serveur**. Le build Vite produit des fichiers statiques
(`index.html` et `app/*.js`, `app/*.css`) que le workflow copie dans le `public/` de Symfony.
Le serveur n'héberge donc qu'un seul site, servi par OpenLiteSpeed et PHP. Il n'y a pas de
Node et pas de second processus.

Trajet d'une visite sur `https://<domaine>/instruments/12` :

```
Navigateur ──► OpenLiteSpeed (HTTPS) ──► public/.htaccess
                                           │
        ┌──────────────────────────────────┼──────────────────────────┐
        ▼                                  ▼                          ▼
 fichier existant             /api, /admin, /bundles, /_*     toute autre URL
 (app/*.js, assets/, uploads)   → index.php (Symfony)          → index.html (React)
 servi tel quel                 → réponse JSON                 → React Router prend le relais
```

1. Aucun fichier ne correspond à ce chemin, et il ne commence pas par `/api` :
   OpenLiteSpeed renvoie `index.html`.
2. Le navigateur charge `/app/index-<hash>.js`, un fichier réel servi directement.
3. React Router lit l'URL et exécute le loader, qui appelle
   `fetch('/api/instruments/12')` (`frontend/src/loaders.js`).
4. Cette URL commence par `/api` : la requête est transmise à `index.php` et API Platform
   répond en JSON-LD.

**Pourquoi aucune configuration n'est nécessaire** : le client API
(`frontend/src/api/client.js`) utilise des URL **relatives** (`/api/...`) et
`credentials: 'same-origin'`. React et l'API partagent la même origine, donc :

- il n'y a pas d'URL d'API à définir selon l'environnement : `/api` désigne toujours le
  serveur qui a servi la page ;
- il n'y a pas de CORS : le navigateur ne voit qu'une seule origine ;
- le cookie de session Symfony (connexion par `/api/login`) accompagne automatiquement
  chaque appel.

En dev, le proxy Vite (`frontend/vite.config.js`) reproduit cette même origine en relayant
`/api`, `/admin`, `/bundles` et `/uploads` vers le conteneur nginx. Le code React est donc
identique en dev et en prod.

`CORS_ALLOW_ORIGIN` (voir 2.5) peut rester restreint au domaine : NelmioCors ne servira que
si un autre domaine appelle l'API un jour.

**Cas où il faudrait plus de configuration** : React hébergé sur un domaine différent de
celui de l'API (`www.` et `api.`, application mobile…). Il faudrait alors une variable
`VITE_API_URL` au moment du build, une configuration CORS avec `allow_credentials: true`,
et soit un cookie `SameSite=None; Secure`, soit une authentification par jeton (JWT).
L'architecture actuelle, sur un seul domaine, évite cette complexité.

---

## 2. Préparation du serveur (une fois par environnement)

À faire pour la **préprod** (ex. `preprod.<domaine>`) puis pour la **production**.

### 2.1 CyberPanel

1. **Websites → Create Website** : domaine (ou sous-domaine), version PHP **8.3**.
   Noter l'**utilisateur système** créé pour le site (Websites → List Websites → Manage).
2. **SSL** : *Websites → List Websites → Manage → Issue SSL* (Let's Encrypt). À faire
   **maintenant**, avant de remplacer `public_html` (§ 2.4). Le domaine doit déjà pointer
   vers le VPS (DNS).
3. **Extensions PHP** : *Server → PHP → Install Extensions*, choisir `lsphp83`, installer au
   besoin `intl`, `mysql(nd)`, `opcache`, `mbstring`, `zip`, `gd`, `apcu` (facultatif).
   Vérification en SSH : `/usr/local/lsws/lsphp83/bin/php -m`.
4. **Réglages PHP** : *Server → PHP → Edit PHP Configs* (`lsphp83`) :
   `memory_limit = 256M`, `upload_max_filesize = 20M`, `post_max_size = 25M`.
5. **Base de données** : *Databases → Create Database*, rattachée au site. Noter le nom
   exact de la base et de l'utilisateur affichés (CyberPanel peut les préfixer).
6. **Accès SSH** de l'utilisateur du site : *Websites → List Websites → Manage → SSH Access*.
   Si sa connexion est refusée faute de shell, en `root` :
   `usermod -s /bin/bash <utilisateur>`.
7. **Pare-feu** : *Security → Firewall*, vérifier que le port SSH est ouvert.

### 2.2 Clé SSH de déploiement

Sur votre poste, générer une clé **réservée à GitHub** (sans phrase de passe) :

```bash
ssh-keygen -t ed25519 -C "github-deploy-instruments" -f ~/.ssh/instruments_deploy -N ""
```

Ajouter la clé publique à l'utilisateur **du site** (pas `root`), en `root` sur le VPS :

```bash
U=<utilisateur>; H=/home/<domaine>
install -d -m 700 -o $U -g $U $H/.ssh
echo "<contenu de ~/.ssh/instruments_deploy.pub>" >> $H/.ssh/authorized_keys
chown $U:$U $H/.ssh/authorized_keys && chmod 600 $H/.ssh/authorized_keys
```

Si la connexion par clé échoue encore, `sshd` refuse sans doute un dossier personnel
modifiable par le groupe : `chmod g-w,o-w $H` (et consulter `/var/log/auth.log`).

Test depuis votre poste :

```bash
ssh -i ~/.ssh/instruments_deploy -p <port> <utilisateur>@<hôte> "whoami && pwd"
```

Récupérer l'empreinte du serveur pour `SSH_KNOWN_HOSTS` :

```bash
ssh-keyscan -p <port> <hôte> 2>/dev/null
```

Vérifier l'empreinte affichée avec celle du serveur
(`ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub` sur le VPS) : c'est elle qui protège le
déploiement contre une usurpation du serveur.

### 2.3 Dossiers et secrets sur le serveur

Connecté en SSH **avec l'utilisateur du site** :

```bash
DEPLOY_PATH=/home/<domaine>/instruments
mkdir -p $DEPLOY_PATH/{releases,shared/public/uploads,shared/var/log,shared/sauvegardes}
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
dans `DATABASE_URL` doivent être encodés (`@` → `%40`, `#` → `%23`, etc.). Ajuster
`serverVersion` à la version réelle de MariaDB (`mariadb --version`).

### 2.4 Racine du site

Toujours avec l'utilisateur du site, remplacer `public_html` par un lien vers la version en
ligne (le lien reste « cassé » jusqu'au premier déploiement, c'est normal) :

```bash
cd /home/<domaine>
mv public_html public_html.origine      # page par défaut de CyberPanel, à supprimer plus tard
ln -s instruments/current/public public_html
```

Dans *Websites → List Websites → Manage → vHost Conf* (compte administrateur), vérifier :

```
index  {
  useServer               0
  indexFiles              index.html, index.php
}

rewrite  {
  enable                  1
  autoLoadHtaccess        1
}
```

`autoLoadHtaccess` fait lire `public/.htaccess` par OpenLiteSpeed. Si les routes React
(`/connexion`…) répondent 404 après le premier déploiement, redémarrer OpenLiteSpeed :
*Server Status → LiteSpeed Status → Reboot LiteSpeed* (ou `systemctl restart lsws` en root).

En-têtes de cache (facultatif) : OpenLiteSpeed ignore les directives `Header` / `Expires`
des `.htaccess`. Par défaut, `index.html` n'est pas mis en cache longtemps, ce qui suffit.
Pour un cache long sur `app/` et `assets/` (fichiers versionnés), ajouter un contexte
statique dans *vHost Conf* :

```
context /app/ {
  location                $DOC_ROOT/app/
  allowBrowse             1
  enableExpires           1
  expiresByType           */*=A31536000
}
```

(idem pour `/assets/`).

### 2.5 GitHub

Liste détaillée et commandes : [`github-variables.md`](github-variables.md).

**Settings → Environments** : créer `dev` (préprod) et `production`.

| Nom | Type | Exemple |
|---|---|---|
| `SSH_HOST` | secret | `vps.mondomaine.be` |
| `SSH_PORT` | secret | `22` |
| `SSH_USER` | secret | utilisateur système du site |
| `SSH_PRIVATE_KEY` | secret | contenu de `~/.ssh/instruments_deploy` (clé privée) |
| `SSH_KNOWN_HOSTS` | secret | sortie de `ssh-keyscan` |
| `DEPLOY_PATH` | variable | `/home/<domaine>/instruments` |
| `SITE_URL` | variable | `https://<domaine>` (sans `/` final) |
| `PHP_BIN` | variable (facultative) | défaut : `/usr/local/lsws/lsphp83/bin/php` |
| `RECHARGEMENT_PHP` | variable (conseillée) | `pkill -u "$(id -un)" -x lsphp \|\| true` (voir § 5) |

Pour `production` :
- **Required reviewers** : vous-même (le déploiement attend votre validation). Sur un dépôt
  **privé**, cette option demande un compte GitHub Pro / Team ; à défaut, le tag reste la
  seule action volontaire qui déclenche la production ;
- **Deployment branches and tags** : limiter aux tags `v*`.

Pour `dev` (préprod) : limiter à la branche `main`.

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
7. relance de PHP si `RECHARGEMENT_PHP` est défini ;
8. suppression des versions au-delà des 5 dernières.

Le workflow vérifie ensuite `/api/health` (connexion à la base comprise), `/` et `/admin/login`.
Si une étape échoue avant la bascule, **la version en ligne n'est pas touchée**.

---

## 4. Revenir en arrière

```bash
ssh <utilisateur>@<hôte>
cd /home/<domaine>/instruments
bash current/deploy/revenir-version.sh "$PWD"
```

`current` repointe vers la version précédente ; la version fautive est renommée `*.echec`.

⚠️ Les **migrations ne sont pas annulées**. Si la version fautive a modifié le schéma :

```bash
gunzip < shared/sauvegardes/<base>_<date>.sql.gz | mysql <base>
```

---

## 5. Points d'attention

- **OPcache et cache des chemins** : après la bascule du lien `current`, les processus
  `lsphp` peuvent servir l'ancienne version (OPcache, cache realpath de PHP). La variable
  GitHub `RECHARGEMENT_PHP` = `pkill -u "$(id -un)" -x lsphp || true` arrête les processus
  PHP du site ; OpenLiteSpeed les relance à la requête suivante. Ils tournent sous
  l'utilisateur du site : aucun droit `root` n'est nécessaire. À vérifier lors du premier
  déploiement (`ps -u <utilisateur> -o pid,comm` doit montrer des processus `lsphp`).
- **Fixtures** : ne **jamais** lancer `doctrine:fixtures:load` en préprod ou en production :
  la commande vide la base. Le bundle n'est d'ailleurs pas installé (`--no-dev`).
- **Premier compte administrateur** : à créer à la main après le premier déploiement.
  Hacher le mot de passe sur le serveur avec `php bin/console security:hash-password`
  (depuis `current/`), puis insérer l'utilisateur en SQL avec `roles = '["ROLE_ADMIN"]'`.
  Une commande dédiée (`app:creer-admin`) serait plus sûre : à prévoir.
- **Tests** : la CI lance PHPUnit, mais le projet n'a encore aucun test. Le garde-fou
  actuel se limite aux lints, aux migrations sur une base vierge et à `doctrine:schema:validate`.
- **Journaux** : `shared/var/log/prod.log`, conservés d'une version à l'autre. Journaux
  d'OpenLiteSpeed : `/home/<domaine>/logs/`.
