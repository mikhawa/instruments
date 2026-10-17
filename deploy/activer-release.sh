#!/usr/bin/env bash
# Active une version déjà décompressée sur le serveur (appelé par GitHub Actions).
#
#   deploy/activer-release.sh <DEPLOY_PATH>
#
# À lancer depuis le dossier de la version : <DEPLOY_PATH>/releases/<horodatage>
# Structure attendue (voir docs/devops/vps-preprod.md) :
#   <DEPLOY_PATH>/shared/.env.local        secrets de l'environnement
#   <DEPLOY_PATH>/shared/public/uploads/   fichiers envoyés (images)
#   <DEPLOY_PATH>/shared/var/log/          journaux
#   <DEPLOY_PATH>/shared/sauvegardes/      dumps SQL avant migration
#   <DEPLOY_PATH>/current -> releases/...  version servie par Apache
#
# Variables facultatives :
#   PHP_BIN             binaire PHP (défaut : PHP 8.3 de Plesk)
#   VERSIONS_CONSERVEES nombre de versions gardées (défaut : 5)
#   RECHARGEMENT_PHP    commande pour recharger PHP-FPM / vider OPcache après bascule

set -euo pipefail

DEPLOY_PATH="${1:?Usage : $0 <DEPLOY_PATH>}"
PHP="${PHP_BIN:-/opt/plesk/php/8.3/bin/php}"
VERSIONS_CONSERVEES="${VERSIONS_CONSERVEES:-5}"
RELEASE="$(pwd -P)"
SHARED="$DEPLOY_PATH/shared"

# L'environnement Symfony vient uniquement de shared/.env.local :
# une variable héritée de la session SSH l'emporterait sur ce fichier
unset APP_ENV APP_DEBUG

etape() { printf '\n▶ %s\n' "$*"; }

etape "Vérifications"
[[ "$RELEASE" == "$DEPLOY_PATH/releases/"* ]] || { echo "À lancer depuis $DEPLOY_PATH/releases/<version>" >&2; exit 1; }
[[ -f "$SHARED/.env.local" ]] || { echo "Fichier manquant : $SHARED/.env.local" >&2; exit 1; }
"$PHP" -v | head -n 1
mkdir -p "$SHARED/public/uploads" "$SHARED/var/log" "$SHARED/sauvegardes"

etape "Liens vers les fichiers partagés"
ln -sfn "$SHARED/.env.local" .env.local
rm -rf public/uploads var/log
mkdir -p var
ln -sfn "$SHARED/public/uploads" public/uploads
ln -sfn "$SHARED/var/log" var/log

etape "Sauvegarde de la base avant migration"
# Identifiants lus depuis la configuration Symfony, passés à mysqldump par un fichier
# temporaire (jamais en argument : ils seraient visibles dans la liste des processus)
IDENTIFIANTS="$(mktemp)"
trap 'rm -f "$IDENTIFIANTS"' EXIT
chmod 600 "$IDENTIFIANTS"
BASE="$("$PHP" -r '
    require "vendor/autoload.php";
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
    $u = parse_url($_SERVER["DATABASE_URL"]);
    file_put_contents($argv[1], sprintf("[client]\nuser=%s\npassword=%s\nhost=%s\nport=%d\n",
        urldecode($u["user"] ?? ""), urldecode($u["pass"] ?? ""), $u["host"] ?? "localhost", $u["port"] ?? 3306));
    echo ltrim($u["path"] ?? "", "/");
' "$IDENTIFIANTS")"
if command -v mysqldump >/dev/null 2>&1; then
    DUMP="$SHARED/sauvegardes/${BASE}_$(date +%Y%m%d-%H%M%S).sql.gz"
    mysqldump --defaults-extra-file="$IDENTIFIANTS" --single-transaction --routines --no-tablespaces "$BASE" | gzip > "$DUMP"
    echo "Sauvegarde : $DUMP"
    ls -1t "$SHARED/sauvegardes/${BASE}_"*.sql.gz | tail -n +11 | xargs -r rm -f
else
    echo "mysqldump introuvable : sauvegarde ignorée (vérifier les sauvegardes Plesk)"
fi

etape "Migrations"
"$PHP" bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

etape "Préchauffage du cache"
"$PHP" bin/console cache:warmup

etape "Bascule vers la nouvelle version"
# Remplacement atomique du lien : aucune requête ne voit un état intermédiaire
ln -sfn "$RELEASE" "$DEPLOY_PATH/current.tmp"
mv -Tf "$DEPLOY_PATH/current.tmp" "$DEPLOY_PATH/current"
echo "current -> $RELEASE"

if [[ -n "${RECHARGEMENT_PHP:-}" ]]; then
    etape "Rechargement de PHP"
    eval "$RECHARGEMENT_PHP"
fi

etape "Nettoyage des anciennes versions"
ls -1dt "$DEPLOY_PATH"/releases/*/ | tail -n +"$((VERSIONS_CONSERVEES + 1))" | xargs -r rm -rf
ls -1dt "$DEPLOY_PATH"/releases/*/

etape "Déploiement terminé"
