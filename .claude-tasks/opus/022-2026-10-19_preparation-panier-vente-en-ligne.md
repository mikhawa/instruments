# 022 — Préparation : panier et systèmes de vente en ligne

- **Date** : 2026-10-19
- **Modèle** : Opus
- **Justification** : choix d'architecture (panier, tunnel de commande, paiement, concurrence sur le stock), sécurité et contraintes fiscales et juridiques → Opus.

## Fichiers créés

- `prepare/README.md` — sommaire, recommandation, tableau des décisions D1 à D12
- `prepare/01-panier.md` — 4 options de stockage, API, fusion, sécurité
- `prepare/02-tunnel-commande.md` — étapes, commande invité, réservation du stock, Workflow, calcul des montants
- `prepare/03-paiement.md` — prestataires, modes d'intégration, webhooks, virement SEPA, paiement en plusieurs fois
- `prepare/04-modeles-de-vente.md` — 21 modèles de vente (offre, acompte, précommande, location, dépôt-vente…)
- `prepare/05-livraison.md` — calcul des frais de port, transporteurs, assurance, international, retours
- `prepare/06-fiscalite-et-juridique.md` — OSS, régime de la marge, factures, Peppol, rétractation, CITES, RGPD
- `prepare/07-alternatives-et-canaux.md` — maison / Sylius / SaaS, Reverb, eBay, Google Merchant
- `prepare/08-feuille-de-route.md` — phases, modèle Claude par tâche, évolutions du schéma, tests

## Résumé

Documentation de préparation uniquement, aucun code modifié. Recommandations principales : panier en base de données (jeton en cookie), réservation du stock au checkout, commande invité, Mollie en paiement hébergé, virement SEPA pour les gros montants, champ `regime_tva` (régime de la marge) et contrôle CITES.
