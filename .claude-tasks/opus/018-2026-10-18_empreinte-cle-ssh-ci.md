# 018 — Affichage de l'empreinte de la clé SSH dans la CI

- **Modèle** : Opus
- **Justification** : modification CI/CD d'une ligne (niveau Sonnet), faite directement dans la session Opus de diagnostic SSH plutôt que déléguée.
- **Fichiers modifiés** : `.github/workflows/deploy.yml`

## Résumé

Le VPS refusait la clé envoyée par GitHub Actions : `/var/log/secure` montrait l'empreinte
`SHA256:NoBxO9…`, alors que `authorized_keys` contient `SHA256:mBSBYynI…`. Le secret
`SSH_PRIVATE_KEY` effectivement lu par le job n'est donc pas celui mis à jour.

L'étape « Clé SSH » affiche maintenant `ssh-keygen -lf ~/.ssh/deploy` : seule l'empreinte de la
clé reçue est visible dans le log (jamais la clé), ce qui permet de la comparer avec
`ssh-keygen -lf <home>/.ssh/authorized_keys` sur le serveur.
