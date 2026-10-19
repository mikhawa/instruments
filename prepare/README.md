# Préparation : panier et vente en ligne

> Dossier de **préparation** (aucun code n'est encore écrit). Il présente les options possibles avant de décider.
> Rédigé le 2026-10-19 à partir de l'état du dépôt : schéma BDD v1 (`docs/architecture/database-schema.md`), API Platform 5, React 19 + React Router, session Symfony partagée (`json_login`).

## Sommaire

| Fichier | Sujet | Décisions à prendre |
|---|---|---|
| [01-panier.md](01-panier.md) | Où et comment stocker le panier, API, fusion invité → compte | D1, D2 |
| [02-tunnel-commande.md](02-tunnel-commande.md) | Étapes du checkout, commande invité, réservation de stock, Workflow | D3, D4 |
| [03-paiement.md](03-paiement.md) | Prestataires (Mollie, Stripe, PayPal…), modes d'intégration, webhooks, virement | D5, D6, D7 |
| [04-modeles-de-vente.md](04-modeles-de-vente.md) | Prix fixe, pièce unique, faire une offre, acompte, précommande, location, dépôt-vente, enchères… | D8 |
| [05-livraison.md](05-livraison.md) | Calcul des frais de port, transporteurs, emballage, retrait sur place | D9 |
| [06-fiscalite-et-juridique.md](06-fiscalite-et-juridique.md) | TVA (OSS, **régime de la marge**), factures, rétractation, garanties, **CITES** | D10, D11 |
| [07-alternatives-et-canaux.md](07-alternatives-et-canaux.md) | Faire soi-même, Sylius, solutions SaaS, places de marché (Reverb, eBay, Etsy) | D12 |
| [08-feuille-de-route.md](08-feuille-de-route.md) | Ordre de réalisation, nouvelles tables, modèle Claude conseillé par étape | — |

## Recommandation en une page

Le projet vend des objets **chers, fragiles, souvent uniques**, à un public de **passionnés et de collectionneurs**, depuis la **Belgique**. Ces quatre points orientent toutes les recommandations.

| Sujet | Recommandation | Pourquoi |
|---|---|---|
| Panier | **Panier en base de données**, identifié par un jeton (cookie) pour l'invité, rattaché au compte à la connexion | Le serveur reste la seule source des prix et de la disponibilité ; possibilité de relancer un panier abandonné ; multi-appareils |
| Réservation du stock | **À l'entrée dans le paiement**, pas à l'ajout au panier (15 à 30 min) | Le panier n'immobilise pas une pièce unique ; la réservation ne dure que pendant le paiement |
| Commande sans compte | **Oui** (commande « invité » avec e-mail), création de compte proposée après l'achat | Moins d'abandons ; le schéma actuel impose un utilisateur, il faut l'adapter |
| Prestataire de paiement | **Mollie** (paiement hébergé), Stripe en alternative | Bancontact, cartes, PayPal, Apple Pay, Klarna depuis un seul contrat ; société européenne ; sans abonnement |
| Gros montants | **Virement SEPA avec QR code** en plus du paiement en ligne | Pour une pièce à 4 000 €, la commission carte (~70 €) devient importante ; réservation plus longue (3 à 5 jours) |
| Intégration | **Adaptateur maison** (`PrestatairePaiementInterface`) + SDK officiel, pas de Payum | Possible de changer de prestataire ; peu de dépendances |
| Modèles de vente | Prix fixe, puis **« Prévenez-moi »**, puis **« Faire une offre »** et **réservation avec acompte** pour les pièces de collection | Pratiques courantes chez les collectionneurs, pour un coût modéré |
| Livraison | Grille **zone × poids** en base, plus un drapeau **« sur devis / retrait uniquement »** pour les grands instruments ; Sendcloud plus tard | `poids_grammes` existe déjà ; une harpe ou une contrebasse ne se tarifie pas au poids |
| TVA | Prévoir dès maintenant un champ **`regime_tva`** (`normal` / `marge`) sur l'instrument | Les instruments anciens et d'occasion relèvent souvent du **régime de la marge** : cela change la facture et le calcul |
| Juridique | Contrôle **CITES** (palissandre, ivoire, écaille, peau de python…) en fiche produit et au moment du paiement | Risque de saisie en douane et de sanction ; les instruments traditionnels sont très concernés |

## Décisions à prendre (récapitulatif)

| # | Question | Options | Recommandé |
|---|---|---|---|
| D1 | Stockage du panier | A localStorage · B session PHP · C base de données · D hybride | **C** |
| D2 | Réservation du stock | à l'ajout · au checkout · au paiement confirmé | **au checkout** |
| D3 | Commande sans compte | interdite · invité pur · compte léger automatique | **invité + compte proposé** |
| D4 | Délai d'expiration d'une commande non payée | 15 min · 30 min · variable selon le moyen de paiement | **variable** (30 min en ligne, 5 jours en virement) |
| D5 | Prestataire principal | Mollie · Stripe · PayPal · Adyen · Viva | **Mollie** |
| D6 | Mode d'intégration | redirection hébergée · composants intégrés · formulaire maison | **redirection hébergée** (v1) |
| D7 | Paiement en plusieurs fois | non · Klarna · Alma · in3 | **Klarna via Mollie** (v2) |
| D8 | Modèles de vente en plus du prix fixe | voir 04 | **offre + acompte + alerte** |
| D9 | Frais de port | forfait · poids · zone × poids · API transporteur | **zone × poids** puis Sendcloud |
| D10 | Régime de TVA | normal seul · normal + marge | **normal + marge** |
| D11 | Pays de livraison | BE · UE · monde | **UE** (v1), monde plus tard |
| D12 | Construire ou utiliser une plateforme | maison · Sylius · SaaS | **maison** (le socle existe déjà) |

> Ces documents restent un **support de décision**. Les montants (commissions, tarifs) sont **indicatifs** et à vérifier sur les grilles tarifaires en vigueur. Les points fiscaux et juridiques sont à **valider avec un comptable ou un juriste** belge.
