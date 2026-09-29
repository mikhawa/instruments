# Variables et secrets GitHub pour le déploiement

À déclarer dans **GitHub → dépôt `mikhawa/instruments` → Settings → Environments**.
Créer deux environnements, **`preprod`** et **`production`**, et y ajouter les valeurs ci-dessous.
Chaque environnement a **ses propres valeurs**.

> Ne jamais mettre ces valeurs dans le dépôt, ni dans un fichier commité.
> Procédure complète du serveur : [`vps-preprod.md`](vps-preprod.md).

---

## 1. Secrets (Environment secrets)

Chiffrés par GitHub, jamais affichés dans les journaux.

| Nom | Obligatoire | Description | Exemple / comment l'obtenir |
|---|---|---|---|
| `SSH_HOST` | oui | Adresse du VPS | `vps.mondomaine.be` ou `203.0.113.10` |
| `SSH_PORT` | oui | Port SSH du VPS | `22` (ou le port personnalisé) |
| `SSH_USER` | oui | Utilisateur système **du domaine** dans Plesk (pas `root`) | Plesk → Sites Web & Domaines → le domaine → Connexion Web & FTP |
| `SSH_PRIVATE_KEY` | oui | Clé **privée** dédiée au déploiement, contenu complet du fichier | voir § 3.1 — commence par `-----BEGIN OPENSSH PRIVATE KEY-----` |
| `SSH_KNOWN_HOSTS` | oui | Empreinte du serveur (protège contre l'usurpation du serveur) | voir § 3.2 |

## 2. Variables (Environment variables)

Non chiffrées, visibles dans les journaux : aucune donnée sensible ici.

| Nom | Obligatoire | Description | Exemple |
|---|---|---|---|
| `DEPLOY_PATH` | oui | Dossier du projet sur le serveur, **sans `/` final** | `/var/www/vhosts/mondomaine.be/instruments` |
| `SITE_URL` | oui | URL publique, **sans `/` final** (sert aux vérifications après déploiement) | `https://mondomaine.be` |
| `PHP_BIN` | non | Binaire PHP CLI de Plesk | défaut : `/opt/plesk/php/8.3/bin/php` |
| `RECHARGEMENT_PHP` | non | Commande lancée après la bascule pour vider OPcache | `sudo /usr/sbin/service plesk-php83-fpm reload` (nécessite une règle sudo, voir `vps-preprod.md` § 5) |

## 3. Valeurs à préparer

### 3.1 Clé SSH de déploiement

Sur votre poste, **une clé par environnement** (ou une seule, au choix) :

```bash
ssh-keygen -t ed25519 -C "github-deploy-instruments" -f ~/.ssh/instruments_deploy -N ""
```

- `~/.ssh/instruments_deploy.pub` (clé **publique**) → à ajouter sur le serveur dans
  `~/.ssh/authorized_keys` de l'utilisateur `SSH_USER` ;
- `~/.ssh/instruments_deploy` (clé **privée**) → contenu complet à coller dans `SSH_PRIVATE_KEY` :

```bash
cat ~/.ssh/instruments_deploy
```

Tester la connexion avant de configurer GitHub :

```bash
ssh -i ~/.ssh/instruments_deploy -p <SSH_PORT> <SSH_USER>@<SSH_HOST> "echo ok && ls <DEPLOY_PATH>"
```

### 3.2 Empreinte du serveur

```bash
ssh-keyscan -p <SSH_PORT> <SSH_HOST> 2>/dev/null
```

Coller **toutes les lignes** obtenues dans `SSH_KNOWN_HOSTS`. Comparer l'empreinte avec celle
connue du serveur (`ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub` sur le VPS).

## 4. Réglages des environnements

| Environnement | Déclenché par | Deployment branches and tags | Required reviewers |
|---|---|---|---|
| `preprod` | push sur `main` | branche `main` | — |
| `production` | tag `v*` | tags `v*` | vous-même (option payante sur un dépôt privé) |

## 5. Configuration en ligne de commande (facultatif)

Avec [GitHub CLI](https://cli.github.com/) (`gh auth login` au préalable), par exemple pour la préprod :

```bash
ENV=preprod
gh secret set SSH_HOST        --env $ENV --body "vps.mondomaine.be"
gh secret set SSH_PORT        --env $ENV --body "22"
gh secret set SSH_USER        --env $ENV --body "utilisateur_plesk"
gh secret set SSH_PRIVATE_KEY --env $ENV < ~/.ssh/instruments_deploy
ssh-keyscan -p 22 vps.mondomaine.be 2>/dev/null | gh secret set SSH_KNOWN_HOSTS --env $ENV

gh variable set DEPLOY_PATH --env $ENV --body "/var/www/vhosts/preprod.mondomaine.be/instruments"
gh variable set SITE_URL    --env $ENV --body "https://preprod.mondomaine.be"
```

Répéter avec `ENV=production` et les valeurs de production.
L'environnement doit exister avant (Settings → Environments → New environment).

## 6. À ne pas confondre : les secrets de l'application

Les secrets **Symfony** (`APP_SECRET`, `DATABASE_URL`, `MAILER_DSN`, etc.) ne vont **pas** dans
GitHub : ils sont uniquement sur le serveur, dans `<DEPLOY_PATH>/shared/.env.local`
(voir `vps-preprod.md` § 2.3).

## 7. Aide-mémoire

```
Environnement « preprod » et « production »
├── Secrets
│   ├── SSH_HOST
│   ├── SSH_PORT
│   ├── SSH_USER
│   ├── SSH_PRIVATE_KEY
│   └── SSH_KNOWN_HOSTS
└── Variables
    ├── DEPLOY_PATH
    ├── SITE_URL
    ├── PHP_BIN            (facultatif)
    └── RECHARGEMENT_PHP   (facultatif)
```
