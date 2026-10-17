# 014 — Passage du déploiement de Plesk à CyberPanel (OpenLiteSpeed)

- **Modèle utilisé** : Opus
- **Justification** : changement d'infrastructure de déploiement (serveur web, chemins, PHP,
  rechargement OPcache) touchant le workflow, le script serveur, le routage et la documentation.

## Fichiers modifiés

- `.github/workflows/deploy.yml` : commentaire d'en-tête, `PHP_BIN` par défaut `lsphp83`
- `deploy/activer-release.sh` : `PHP_BIN` par défaut `/usr/local/lsws/lsphp83/bin/php`, commentaires
- `public/.htaccess` : réécrit pour OpenLiteSpeed (règles de réécriture seules ; suppression de
  `<If>`, `Header`, `Expires`, `R=404`, `E=HTTP_AUTHORIZATION` et de la redirection `index.php`
  fondée sur `REDIRECT_STATUS`, non fiables ou ignorés par OLS)
- `docs/devops/vps-preprod.md` : procédure CyberPanel complète (utilisateur du site,
  `/home/<domaine>`, `public_html` en lien vers `instruments/current/public`, vHost Conf,
  SSL, extensions lsphp83, relance `lsphp`)
- `docs/devops/github-variables.md` : chemins et valeurs CyberPanel
- `CLAUDE.md`, `README.md`, `docs/devops/docker-setup.md`, `explication/01-react-et-securite-api.md` :
  mentions de Plesk remplacées

## Résumé

- `DEPLOY_PATH` = `/home/<domaine>/instruments` ; `public_html` devient un lien vers
  `instruments/current/public` (préserve le renouvellement Let's Encrypt de CyberPanel).
- OPcache : `RECHARGEMENT_PHP` = `pkill -u "$(id -un)" -x lsphp || true` (sans root).
- Points à vérifier au premier déploiement : lecture du `.htaccess` par OLS
  (`autoLoadHtaccess`), arrêt effectif des processus `lsphp`.
