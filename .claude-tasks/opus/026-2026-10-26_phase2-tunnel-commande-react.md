# 026 — Phase 2 : tunnel de commande React (2.6) et branchement des pages de commande

- **Date** : 2026-10-26
- **Modèle** : Opus
- **Justification** : la grille de `CLAUDE.md` classe ce travail front en Sonnet ; il a été fait en Opus dans la même session que l'examen de l'API de commande (contrat de `POST /api/commandes`, total attendu, clé d'idempotence, réponses 409) pour garder un tout cohérent.

## Fichiers créés

- `frontend/src/pages/Commande.jsx` — page `/commande` : coordonnées (e-mail si visiteur), pays et mode de livraison, adresse de livraison, facturation (identique ou distincte, obligatoire en retrait), message, récapitulatif, acceptation des CGV

## Fichiers modifiés

- `frontend/src/router.jsx` — routes `/commande`, `/commande/confirmation/:numero`, `/commande/suivi/:numero`, `/compte/commandes`, `/cgv` (les pages et loaders existaient sans être branchés)
- `frontend/src/pages/Panier.jsx` — bouton « Commander » actif (lien vers `/commande`) quand le panier est commandable
- `frontend/src/components/Layout.jsx` — lien « Mes commandes » pour un client connecté
- `frontend/src/index.css` — styles du tunnel, du détail de commande, de la frise de statut, des pastilles de statut et de la liste « Mes commandes »
- `prepare/08-feuille-de-route.md` — phase 2 marquée réalisée, avec les écarts

## Résumé

- Le total envoyé (`totalTtcAttendu`) = total du panier + prix du mode de livraison choisi ; le serveur refuse (409) s'il a changé, React Router recharge alors le panier et le récapitulatif affiche le nouveau total.
- Clé d'idempotence générée au montage, renouvelée après chaque réponse du serveur (refus), conservée après une coupure réseau.
- Bouton « Valider » et sélecteur de pays désactivés pendant un envoi ou un rechargement : impossible d'envoyer un total périmé.
- Messages de livraison du serveur traduits (pays non livrable, retrait uniquement, transport sur devis).
- Client connecté : e-mail du compte, prénom et nom préremplis.

## Vérifications

- `npm run lint` (0 erreur) et `vite build` OK (dans le conteneur `node`).
- Parcours testés dans Chromium (Playwright, image Docker `mcr.microsoft.com/playwright:v1.48.0-jammy`, scripts hors dépôt) :
  - visiteur, livraison en France → confirmation, lien de suivi, page de suivi avec frise ;
  - client connecté, retrait à l'atelier → pas d'adresse de livraison, facturation préremplie → confirmation, « Mes commandes » ;
  - panier modifié pendant la saisie → 409, nouveau total affiché, saisie conservée, nouvelle clé ; second envoi accepté ;
  - mobile 375 px : pas de défilement horizontal.
- Back-end non modifié.

## Limites connues

- Les fichiers `.jsx` et `index.css` modifiés sont en CRLF dans le dépôt alors que `.gitattributes` impose LF ; conservé tel quel pour limiter le diff.
- Les tests ont créé des commandes `CMD-2026-000006` à `000013` dans la base de dev (elles expirent seules au bout de 30 minutes).
