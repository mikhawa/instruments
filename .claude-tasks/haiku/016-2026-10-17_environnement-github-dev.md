# 016 — Environnement GitHub renommé en « dev »

- **Modèle** : Haiku (exécuté par Opus)
- **Justification** : simple renommage de l'environnement GitHub, sans logique métier.

## Fichiers modifiés
- `.github/workflows/deploy.yml`
- `docs/devops/github-variables.md`
- `docs/devops/vps-preprod.md`

## Résumé
L'environnement GitHub de préprod s'appelle `dev` (et non `preprod`). Le job `deploy`
cible désormais `dev` pour les push sur `main` (`environment.name`, groupe de `concurrency`
et libellé du job) ; `production` reste inchangé pour les tags `v*`. La documentation
GitHub est alignée.
