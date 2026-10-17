# 013 — Ajout de .gitattributes (fins de ligne LF)

- **Modèle utilisé** : Opus
- **Justification** : tâche simple (relevant normalement de Haiku), réalisée dans la continuité du diagnostic d'un bug Docker mené avec Opus.

## Contexte

Le conteneur `php` redémarrait en boucle (`exec /usr/local/bin/docker-entrypoint: no such file or directory`).
Cause : les fichiers du dépôt avaient été convertis en CRLF (outil côté Windows), le shebang
`#!/bin/sh\r` du script d'entrée devenait introuvable.

## Fichiers modifiés

- `.gitattributes` (créé)

## Résumé

- Normalisation forcée en LF pour tous les fichiers texte (`* text=auto eol=lf`).
- Types binaires (images, polices, archives, PDF) exclus de toute conversion.
- Correctif ponctuel préalable : reconversion CRLF → LF des fichiers modifiés, reconstruction de l'image `php` et recréation du conteneur `nginx`.
