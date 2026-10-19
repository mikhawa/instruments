# 06 — Fiscalité et juridique

> ⚠️ Ce document présente des **pistes techniques**, pas un avis juridique ni fiscal. Chaque point doit être **validé par un comptable ou un juriste belge** avant la mise en production. Le vendeur est supposé établi en Belgique.

## 1. TVA

### 1.1 Taux et ventes dans l'UE (OSS)
- Taux belge normal : **21 %** (déjà le défaut `taux_tva = 2100`).
- Ventes à distance B2C dans l'UE : dès **10 000 € par an** de ventes vers les autres pays de l'UE (seuil global), on applique **la TVA du pays du client** et on la déclare via le guichet unique **OSS** (One-Stop-Shop).
- Conséquence technique : le taux de TVA ne peut plus être **une colonne fixe de l'instrument** ; il dépend du **pays de livraison**.

Options :

| Option | Description | Quand |
|---|---|---|
| A | TVA belge partout | tant que le seuil de 10 000 € n'est pas dépassé (à surveiller) |
| B ✅ | table `taux_tva_pays` (`pays`, `categorie_tva`, `taux`, `debut_at`) ; l'instrument porte une **catégorie** (`standard`, `reduit`…) | dès le départ, pour être prêt |
| C | service externe (Stripe Tax, Vatstack, Taxdoo) | volume important, nombreux pays |

Les instruments de musique sont au **taux normal** dans presque tous les pays de l'UE. Les livres et partitions imprimées sont souvent au taux réduit.

### 1.2 Régime de la marge (biens d'occasion, objets d'art, d'antiquité et de collection) ⚠️ important
Une grande partie du catalogue est `occasion`, `ancien` ou `restaure`. Quand ces biens ont été **achetés sans TVA déductible** (à un particulier, dans un dépôt-vente, à un autre revendeur sous régime de la marge), le revendeur peut appliquer le **régime particulier d'imposition de la marge bénéficiaire** :
- la TVA ne porte que sur la **marge** (prix de vente − prix d'achat), et non sur le prix total ;
- **la TVA n'apparaît pas sur la facture** et ne peut pas être déduite par l'acheteur ; mention obligatoire du type « Régime particulier – biens d'occasion » / « objets d'antiquité » ;
- ce régime est compatible avec l'OSS : pas de TVA du pays de destination sur une vente sous régime de la marge (la TVA belge s'applique sur la marge).

Impact sur le schéma :

| Ajout | Type | Note |
|---|---|---|
| `instrument.regime_tva` | VARCHAR(20) | enum `RegimeTva` : `normal`, `marge_occasion`, `marge_antiquite`, `marge_art` |
| `instrument.prix_achat` | INT UNSIGNED NULL | centimes, **réservé à l'admin** (jamais exposé dans l'API) ; nécessaire pour calculer la marge |
| `ligne_commande.regime_tva` | copie | |
| `ligne_commande.tva_marge` | INT NULL | TVA due sur la marge (pour la comptabilité, pas pour la facture) |

Le calcul des totaux (02 §6) doit traiter **deux familles de lignes** : TVA « normale » ventilée sur la facture, et lignes « marge » sans TVA visible.

### 1.3 Ventes aux professionnels (B2B)
- Client assujetti dans un **autre État de l'UE** avec un numéro de TVA valide (vérifié via **VIES**) → **autoliquidation** : facture HT, mention « Autoliquidation – article 138 de la directive 2006/112/CE » (ou équivalent belge), déclaration au **listing intracommunautaire**.
- Client assujetti **belge** → TVA belge normale.
- **Facturation électronique obligatoire en B2B en Belgique depuis le 1er janvier 2026** (format structuré via le réseau **Peppol**). Si l'on vend à des professionnels belges (écoles, ensembles), il faut un **point d'accès Peppol** (fourni par un logiciel comptable : Billit, Exact, Odoo, Accountable…) ou une API Peppol.
- **Exportations hors UE** : exonération de TVA, avec preuve d'exportation (document de douane ou suivi du transporteur).

## 2. Factures

### Obligations
- **Numérotation continue**, sans trou, par série (ex. `F2026-000001`). **Ne pas utiliser `commande.id`** : une commande annulée ne doit pas créer de trou dans les factures.
- Mentions : identité et numéro de TVA du vendeur, date, numéro, client, désignation, quantité, prix unitaire HT, taux et montant de TVA **par taux**, total, mention du régime de la marge le cas échéant, mention d'autoliquidation le cas échéant.
- **Facture d'acompte** pour une réservation avec acompte (04 §6).
- **Note de crédit** (avoir) pour tout remboursement, jamais de modification d'une facture émise.
- Conservation : **10 ans** en Belgique (délai actuel pour les pièces comptables ; à confirmer).

### Tables proposées
```
facture (id, numero UNIQUE, type [facture|acompte|note_credit], commande_id, facture_origine_id NULL,
         date_emission, client JSON figé, lignes JSON figées, total_ht, total_tva, total_ttc,
         regime_mentions JSON, fichier_pdf, created_at)
sequence_facture (serie, annee, dernier_numero)   -- incrément sous verrou (SELECT … FOR UPDATE)
```

### Génération PDF
| Solution | + | − |
|---|---|---|
| **dompdf** (`dompdf/dompdf`) | 100 % PHP, rien à installer sur le VPS | CSS limité |
| **Gotenberg** (conteneur Docker, Chromium) | rendu fidèle, `sensiolabs/gotenberg-bundle` | conteneur supplémentaire : facile en dev, **difficile sous CyberPanel** |
| **wkhtmltopdf / KnpSnappy** | connu | projet abandonné |
| Logiciel comptable externe (API) | numérotation et Peppol pris en charge | dépendance, abonnement |

Recommandé : **dompdf** pour démarrer (compatible avec le VPS), ou laisser un **logiciel comptable** émettre les factures via son API si le comptable l'utilise déjà (Peppol et OSS inclus).

## 3. Droit de la consommation (B2C)

| Obligation | Effet dans le site |
|---|---|
| **Information précontractuelle** (Code de droit économique, livre VI) | identité, coordonnées, prix TTC, frais de livraison, délais, garantie, rétractation, **avant** le paiement |
| **Droit de rétractation de 14 jours** à compter de la réception | formulaire type de rétractation (en annexe des CGV + dans l'espace client) ; remboursement sous 14 jours, frais de livraison initiaux compris (au tarif standard) |
| Exceptions | bien **confectionné selon les spécifications** du consommateur (sur mesure) ; contenu numérique téléchargé avec renoncement exprès |
| **Bouton de commande explicite** | « Commander avec obligation de paiement » |
| **Garantie légale de conformité** | **2 ans** pour un bien neuf ; pour un bien **d'occasion**, la durée peut être réduite par contrat à **1 an minimum** en Belgique → à préciser dans les CGV et sur la fiche (`etat`) |
| Prix de référence lors d'une réduction (**Omnibus**) | historique des prix sur 30 jours (04 §16) |
| Avis clients (Omnibus) | indiquer si et comment les avis sont vérifiés |
| Médiation | lien vers le Service de médiation pour le consommateur ; la plateforme européenne de règlement en ligne des litiges (ODR) a été supprimée en 2025 : ne plus y renvoyer |
| **Accessibilité** (European Accessibility Act, en vigueur depuis le 28/06/2025) | le parcours d'achat en ligne doit être accessible (WCAG 2.1 AA) ; exemption pour les **micro-entreprises** (moins de 10 personnes **et** moins de 2 M€ de chiffre d'affaires), à vérifier |

Champ utile : `commande.cgv_version`, et versionnage des CGV traduites (table `document_legal` : type, locale, version, contenu, `publie_at`).

## 4. CITES et matériaux protégés ⚠️ propre aux instruments traditionnels

Beaucoup d'instruments traditionnels contiennent des matériaux d'espèces protégées par la **convention CITES** (règlement (CE) 338/97 dans l'UE) :

| Matériau | Exemples d'instruments | Statut (indicatif, à vérifier) |
|---|---|---|
| **Palissandre de Rio** (*Dalbergia nigra*) | guitares, luths anciens | Annexe I : très restreint, certificat obligatoire, même dans l'UE |
| Autres *Dalbergia*, ébène, bubinga | touches, cordiers, chevalets | dérogation pour les instruments finis depuis 2019 (sauf *D. nigra*) — à vérifier selon l'espèce |
| **Ivoire** (éléphant) | sillets, frettes, cornemuses, archets, pièces anciennes | commerce dans l'UE très restreint depuis 2022 ; exception encadrée pour les instruments de musique **antérieurs à 1975**, avec certificat |
| **Écaille de tortue** | plectres, incrustations | Annexe I |
| **Peau de python** | erhu, sanxian, sanshin | Annexe II : **permis d'exportation** hors UE |
| Peau de lézard ou de serpent, os, corne | percussions, cornemuses, instruments africains et asiatiques | variable selon l'espèce |
| Nacre, corail | incrustations | variable |

Risques : saisie à la douane, amende, responsabilité du vendeur.

Implémentation proposée :
- table `materiau_protege` (`code`, `nom` traduit, `annexe_cites`, `vente_ue_autorisee`, `export_hors_ue` [`libre`, `permis_requis`, `interdit`], `note`) ;
- relation `instrument_materiau_protege` + `numero_certificat` (certificat CITES intra-UE si exigé, réservé à l'admin) ;
- **affichage sur la fiche** (« Contient du palissandre — non expédiable hors UE sans permis ») ;
- **contrôle au checkout** : si le pays de livraison n'est pas autorisé → ligne bloquée ou « livraison sur devis » (le temps d'obtenir un permis) ;
- validation EasyAdmin : impossible de publier une pièce concernée sans certificat quand il est obligatoire.

Autorité compétente en Belgique : **SPF Santé publique — service CITES**.

## 5. RGPD et cookies

| Sujet | Mesure |
|---|---|
| Cookies `panier` et session | **fonctionnels** : pas de consentement, mais à mentionner dans la politique de confidentialité |
| Analytics (Matomo, Plausible) | Plausible ou Matomo sans cookie → pas de bandeau ; Google Analytics → consentement préalable |
| Relance de panier abandonné | client connecté : base « intérêt légitime » ou consentement, avec un lien de désinscription ; **pas de relance des visiteurs** dont on n'a pas l'e-mail |
| Alertes « Prévenez-moi » | double opt-in |
| Durées de conservation | paniers visiteurs : 30 j ; comptes inactifs : à définir ; commandes et factures : 10 ans (obligation légale, données minimales) |
| Droit à l'effacement | **anonymiser** l'utilisateur, conserver la commande (base : obligation légale) |
| Sous-traitants | Mollie, Sendcloud, hébergeur, service d'e-mails : contrats de traitement (DPA) et liste dans la politique de confidentialité |
| Données de paiement | jamais stockées (PSP en mode hébergé) |

## 6. Pages légales à rédiger (et à traduire)

1. Conditions générales de vente (avec le formulaire de rétractation)
2. Mentions légales (BCE, TVA, adresse, e-mail, téléphone) — **exigées par Mollie** pour activer le compte
3. Politique de confidentialité
4. Politique de cookies
5. Livraison et retours
6. Garanties (neuf / occasion / ancien)
7. Information CITES
8. (Option) Conditions des offres, des acomptes et des réservations

Stockage proposé : table `document_legal` (versionnée, traduite), affichage via l'API (`/api/pages/{slug}`), administration dans EasyAdmin.

## 7. Décisions

- **D10** : régime de TVA **normal + marge** ; table des taux par pays prête pour l'OSS.
- **D11** : livraison **BE + UE** au départ ; hors UE après la mise en place du contrôle CITES et des documents de douane.
- Facturation : dompdf maison **ou** logiciel comptable relié par API → à décider avec le comptable (Peppol B2B).
