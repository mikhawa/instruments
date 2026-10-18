# 019 — Diagnostic de la sauvegarde de la base au déploiement

- **Modèle** : Opus
- **Justification** : correctif de script de déploiement (niveau Sonnet), fait directement dans la session Opus de diagnostic du déploiement plutôt que délégué.
- **Fichiers modifiés** : `deploy/activer-release.sh`

## Résumé

Au déploiement, `mysqldump` échouait avec l'erreur 1046 « No database selected ». Le nom de
base extrait de `DATABASE_URL` était donc vide, alors que `shared/.env.local` contient bien
`/mich_instruments`.

- Le script affiche maintenant sur stderr ce qu'il lit dans `DATABASE_URL` : utilisateur,
  hôte, port et base, jamais le mot de passe.
- Il s'arrête avec un message clair si le nom de base est vide, au lieu de l'erreur mysqldump.
- `urldecode` est remplacé par `rawurldecode`, comme Doctrine. Avec `urldecode`, un « + »
  dans le mot de passe devenait une espace pour mysqldump, alors que l'application se
  connectait normalement.
