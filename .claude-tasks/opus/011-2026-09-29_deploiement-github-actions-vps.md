# 011 — Déploiement GitHub Actions → VPS Plesk (nginx + Apache)

- **Modèle** : Opus
- **Justification** : CI/CD (niveau Sonnet selon CLAUDE.md) mais avec des enjeux de sécurité et d'exploitation : clés SSH, secrets, vérification de l'hôte, sauvegarde avant migration, bascule atomique, retour arrière.
- **Date** : 2026-09-29

## Fichiers créés / modifiés
- `.github/workflows/deploy.yml` : tests Symfony (lints, migrations sur MariaDB 11.4, `schema:validate`, PHPUnit) et React (oxlint, build) ; paquet `--no-dev` avec assets Symfony et build React intégré à `public/` ; déploiement SSH vers l'environnement `preprod` (push `main`) ou `production` (tag `v*`) ; vérification `/api/health`, `/`, `/admin/login`
- `deploy/activer-release.sh` : liens vers `shared/`, sauvegarde `mysqldump` (identifiants hors ligne de commande), migrations, `cache:warmup`, bascule atomique de `current`, rechargement PHP facultatif, conservation de 5 versions ; ignore `APP_ENV` / `APP_DEBUG` hérités
- `deploy/revenir-version.sh` : retour à la version précédente (version fautive renommée `*.echec`)
- `public/.htaccess` : routage Apache (fichiers réels → `/api`, `/admin`, `/bundles` → Symfony → 404 pour un fichier statique absent → `index.html`), cache long sur `app/` et `assets/`, `index.html` non mis en cache
- `frontend/vite.config.js` : `build.assetsDir: 'app'` (évite le conflit avec `/assets/` de l'AssetMapper)
- `docs/devops/vps-preprod.md` : procédure Plesk, clé SSH, `shared/.env.local`, environnements et secrets GitHub, déploiement, retour arrière, points d'attention
- `docs/devops/docker-setup.md`, `README.md` : renvois vers la procédure

## Vérification
- `public/.htaccess` testé dans un conteneur `httpd:2.4` : `/`, routes React, `/api`, `/admin`, bundles, `app/*.js`, 404 sur fichier absent, redirection de `/index.php/...`
- `deploy/activer-release.sh` exécuté trois fois dans le conteneur PHP sur une base jetable : liens, migrations, cache `prod`, bascule, nettoyage OK (`mysqldump` absent du conteneur : branche « sauvegarde ignorée » testée)
- `deploy/revenir-version.sh` : deux retours successifs puis « aucune version précédente »
- Job « paquet » simulé : `composer install --no-dev` + `assets:install`, `importmap:install`, `asset-map:compile` en `prod` OK ; `vite build` produit `index.html` + `app/`
- `actionlint` : aucune erreur (3 notes SC2029 volontaires) ; `doctrine:schema:validate` OK ; `npm run lint` OK

## Non testé (nécessite le vrai serveur)
- Connexion SSH réelle, chemins Plesk (`/opt/plesk/php/8.3/bin/php`), `mysqldump` sur le VPS, comportement nginx → Apache de Plesk
