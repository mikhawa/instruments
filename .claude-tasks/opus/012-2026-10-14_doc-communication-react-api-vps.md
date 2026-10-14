# 012 — Documentation : communication React ↔ API sur le VPS

- **Modèle** : Opus
- **Justification** : simple ajout de documentation (niveau Haiku selon CLAUDE.md), rédigé par Opus car il reprend l'explication donnée dans la même session
- **Date** : 2026-10-14

## Fichiers modifiés
- `docs/devops/vps-preprod.md` : nouvelle sous-section « Communication React ↔ API Symfony » dans la partie 1

## Résumé
Explique comment React (build statique dans `public/`) et Symfony communiquent sur le VPS sans Docker :
trajet d'une requête (nginx → Apache → `.htaccess`), même origine (URL relatives, pas de CORS,
cookie de session partagé), équivalence avec le proxy Vite en dev, et configuration nécessaire
si le front et l'API étaient un jour sur des domaines différents.
