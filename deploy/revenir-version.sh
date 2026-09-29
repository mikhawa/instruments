#!/usr/bin/env bash
# Revient à la version précédente (à lancer à la main sur le serveur, en SSH).
#
#   deploy/revenir-version.sh <DEPLOY_PATH>
#
# Attention : les migrations de base de données ne sont PAS annulées.
# Si la version fautive a modifié le schéma, restaurer d'abord la sauvegarde
# correspondante dans <DEPLOY_PATH>/shared/sauvegardes/.

set -euo pipefail

DEPLOY_PATH="${1:?Usage : $0 <DEPLOY_PATH>}"
ACTUELLE="$(readlink -f "$DEPLOY_PATH/current")"
PRECEDENTE="$(ls -1dt "$DEPLOY_PATH"/releases/*/ | sed 's:/$::' | grep -v '\.echec$' | grep -vx "$ACTUELLE" | head -n 1 || true)"

[[ -n "$PRECEDENTE" ]] || { echo "Aucune version précédente disponible." >&2; exit 1; }

ln -sfn "$PRECEDENTE" "$DEPLOY_PATH/current.tmp"
mv -Tf "$DEPLOY_PATH/current.tmp" "$DEPLOY_PATH/current"
# La version fautive est écartée pour qu'un second retour remonte encore d'un cran
mv "$ACTUELLE" "$ACTUELLE.echec"

echo "current -> $PRECEDENTE (version écartée : $ACTUELLE.echec)"
