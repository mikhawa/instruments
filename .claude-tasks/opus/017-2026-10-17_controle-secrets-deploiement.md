# 016 — Contrôle des secrets avant déploiement

- **Modèle** : Opus
- **Justification** : diagnostic d'un échec de déploiement CI (configuration SSH / environnements GitHub).

## Contexte
Le job `deploy` échouait sur `main` avec :
`/home/runner/.ssh/config line 2: no argument after keyword "hostname"` (idem `port`, `user`).
Les secrets `SSH_HOST`, `SSH_PORT`, `SSH_USER` étaient vides pour l'environnement `preprod` :
non définis dans **Settings → Environments → preprod → Secrets** (ou créés comme *variables*,
ou dans un environnement au nom différent).

## Fichiers modifiés
- `.github/workflows/deploy.yml` : nouvelle étape « Contrôle de la configuration » en tête du job
  `deploy`, qui liste chaque secret/variable vide et échoue avec un message explicite.

## Action manuelle requise
Créer les secrets `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS` et
les variables `DEPLOY_PATH`, `SITE_URL` dans l'environnement `preprod` (voir `docs/devops/vps-preprod.md` § 2.5).
