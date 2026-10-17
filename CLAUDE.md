# Projet : Site de présentation et de ventes d'instruments de musique traditionnels, avec gestion des stocks et des commandes.

**Objectif** : Présentation des instruments de musique traditionnels, gestion des stocks et des commandes pour les clients.

**Public cible** : Passionnés de musique traditionnelle, musiciens, collectionneurs, et amateurs d'instruments rares.

**Langue du projet** : Ce projet se fait entièrement en français  : code (commentaires, messages de validation, noms de commits), documentation, et échanges. Par contre il sera mis  en Anglais, Espagnol etc ...

**Stack** : Symfony 7.4 LTS --webapp | PHP 8.3 | API pour communiquer avec React, MariaDB 11.4 | Docker | React on front-end.

**Environnements** : dev (Docker local) → préprod (GitHub CI) → prod (VPS Debian 12.13 sous Plesk).


### Sélection obligatoire avant chaque tâche
Évaluer la complexité et choisir le modèle adapté :
- **Haiku** → CRUD simple, migrations, typos, questions syntaxe
- **Sonnet** → Controllers métier, services, tests, CI/CD
- **Opus** → Architecture, sécurité, refactoring majeur, bugs complexes

Pour déléguer à un modèle supérieur/inférieur, utiliser l'outil `Agent` avec le paramètre `model: "opus"` ou `model: "haiku"`.

### Traçabilité obligatoire (.claude-tasks/)
Pour **toute tâche modifiant du code**, créer un fichier dans `.claude-tasks/` :
- **Chemin** : `.claude-tasks/haiku/`, `.claude-tasks/sonnet/` ou `.claude-tasks/opus/` selon le modèle
- **Format** : `NNN-YYYY-MM-DD_description-courte.md`
- **Numérotation** : auto-incrémentée sur 3 chiffres, globale (tous sous-dossiers confondus), indépendante de `documentations-dev/`
- **Contenu minimal** : modèle utilisé, justification, fichiers modifiés, résumé
- Vérifier le dernier numéro existant dans tous les sous-dossiers de `.claude-tasks/` avant de créer un fichier

## Contexte rapide
- Architecture : MVC + couche Service + Repository pattern
- Auth : Symfony Security (voters custom)
- Frontend : React 19 + Vite (SPA dans `frontend/`) consommant l'API Symfony (`/api/*`) — AssetMapper/ImportMap réservé aux éventuelles pages Twig
- Déploiement : GitHub Actions → SSH VPS

## Fichiers clés à connaître
- Architecture globale : `docs/architecture/overview.md`
- Docker dev : `docs/devops/docker-setup.md`
- Schéma BDD : `docs/architecture/database-schema.md`
- Déploiement : `docs/devops/vps-preprod.md`

## Conventions de code
- Entités : `src/Entity/` — annotations Doctrine en attributs PHP 8
- Services métier : `src/Service/`
- Repositories custom : `src/Repository/`
- Nommage : PascalCase classes, snake_case BDD, camelCase JS
