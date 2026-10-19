# 05 — Livraison

Les instruments sont **fragiles**, parfois **encombrants** (harpe celtique, contrebasse, vielle à roue en étui rigide, gong) et parfois **de grande valeur**. Le calcul des frais de port et le choix du transporteur pèsent directement sur la satisfaction et sur le taux de retour.

## 1. Méthodes de calcul des frais de port (D9)

| Option | Principe | + | − |
|---|---|---|---|
| **A. Forfait unique** | ex. 9,90 € BE, 19,90 € UE | trivial | perte sur les gros colis, surfacturation des petits |
| **B. Forfait + franco** | gratuit au-delà de X € | incitatif | idem A |
| **C. Par poids** | tranches de poids (`poids_grammes` existe déjà) | simple | ignore le volume (un tambour est léger mais volumineux) |
| **D. Zone × poids (ou × classe de colis)** ✅ | grille en base : zone (BE, UE-1, UE-2, monde) × tranche | réaliste, administrable dans EasyAdmin | grille à tenir à jour |
| **E. Tarifs du transporteur en direct** | API Sendcloud / Boxtal / bpost | prix exact, choix du transporteur par le client | dépendance, appel réseau au checkout, coût de l'abonnement |
| **F. Sur devis** | instrument signalé « livraison sur devis » | indispensable pour les objets hors normes | commande non finalisable en ligne → demande de devis (04 §7) |

**Recommandé : D + F**, puis **E (Sendcloud)** quand le volume le justifie.

### Poids volumétrique
Les transporteurs facturent le maximum entre le poids réel et le **poids volumétrique** (L × l × h / 5000 en général). Le champ `dimensions` est aujourd'hui du **texte libre** : il faudrait des colonnes numériques **pour le colis** (et non pour l'instrument) :

| Colonne proposée | Type | Note |
|---|---|---|
| `colis_longueur_cm`, `colis_largeur_cm`, `colis_hauteur_cm` | SMALLINT UNSIGNED NULL | colis emballé |
| `colis_poids_grammes` | INT UNSIGNED NULL | emballage compris |
| `classe_livraison` | VARCHAR(20) | enum : `petit_colis`, `colis`, `hors_norme`, `transport_dedie`, `retrait_uniquement` |

On peut aussi faire **la grille par classe de livraison** plutôt que par poids : plus simple à administrer et assez précis pour des instruments.

### Tables de la grille

```
zone_livraison (id, code, nom)                     -- BE, UE1 (FR, NL, LU, DE), UE2 (reste de l'UE), CH-UK, MONDE
zone_livraison_pays (zone_id, pays CHAR(2))
mode_livraison (id, code, transporteur, suivi, signature, assurance_incluse_max, is_active)
tarif_livraison (id, zone_id, mode_id, classe_livraison | poids_max_grammes, prix_ttc, delai_jours_min, delai_jours_max)
```

Plusieurs articles dans une commande : on additionne les poids et on prend la classe la plus grande. Un article `hors_norme` dans la commande → tout le panier passe en « sur devis » ou en livraison séparée.

## 2. Transporteurs et agrégateurs (depuis la Belgique)

| Solution | Type | Intérêt |
|---|---|---|
| **bpost** (domicile, point relais, Pack Station) | transporteur national | incontournable en BE ; API Shipping Manager |
| **PostNL / DHL eCommerce / DPD / GLS** | transporteurs | bons tarifs pour le Benelux et l'UE ; points relais |
| **DHL Express / UPS / FedEx** | express international | pour les collectionneurs hors UE, avec dédouanement, assurance |
| **Sendcloud** ✅ | agrégateur (société néerlandaise) | étiquettes multi-transporteurs, choix du point relais (widget), suivi par e-mail, retours, API PHP ; offre gratuite limitée puis abonnement |
| **Boxtal** | agrégateur (FR) | similaire, fort sur la France |
| **Packlink** | agrégateur | tarifs négociés, interface simple |
| **Transporteur spécialisé en objets d'art** | transport dédié | pianos, harpes, pièces de musée : sur devis uniquement |

Recommandé : **Sendcloud** en v2 (choix du point relais au checkout, étiquettes depuis EasyAdmin, suivi automatique `numero_suivi`). En v1 : étiquettes créées à la main, numéro de suivi saisi dans EasyAdmin → transition `expedier` → e-mail.

## 3. Assurance et preuve

- **Assurance ad valorem** obligatoire au-delà d'un seuil (ex. 500 €) : l'assurance de base des transporteurs est très faible (souvent quelques centaines d'euros maximum ; liée au poids pour certains).
- **Remise contre signature** pour les envois de valeur.
- Photos de l'emballage avant envoi, stockées sur la commande (`commande_document`) : utile en cas de litige ou de rétrofacturation.
- Champ `commande.valeur_assuree`.

## 4. Emballage

Hors code, mais avec un impact sur les frais :
- étuis rigides, caisse bois pour les pièces anciennes, détente des cordes, humidité (bois, peaux naturelles) ;
- pièces fragiles : double carton + calage ;
- coût d'emballage à inclure dans la grille (ou en ligne « frais d'emballage sécurisé »).

## 5. International

- **UE** : pas de douane, TVA via l'OSS (06).
- **Hors UE** (Suisse, Royaume-Uni, États-Unis, Japon…) : déclaration en douane (**CN23**), code **SH / HS** de l'instrument (chapitre 92 « Instruments de musique »), droits et taxes payés par le destinataire (DAP) ou par l'expéditeur (DDP), facture commerciale.
  - Champ `instrument.code_sh` (ex. `9202` instruments à cordes, `9205` instruments à vent, `9206` percussion).
  - **CITES** : certains matériaux interdisent ou retardent l'export (06). Un contrôle au checkout bloque ou signale.
- Afficher clairement : « Les droits de douane et taxes locales peuvent être à la charge du destinataire ».
- Si un pays n'est pas livrable : message dès la page **panier** (sélecteur de pays).

## 6. Retrait sur place / livraison en main propre

- Mode `retrait_atelier` (0 €), avec choix d'un créneau (table `rendez_vous` ou simple prise de contact).
- Mode `livraison_personnelle` dans un rayon de X km pour les très gros instruments (forfait ou devis).
- Paiement en ligne avant le retrait (recommandé) ou sur place avec un terminal.

## 7. Retours

- Formulaire « **Demander un retour** » dans l'espace client → statut `retour_demande` → étiquette de retour (Sendcloud ou envoi manuel) → réception → contrôle de l'état → `retournee` → remboursement.
- Frais de retour : à la charge du client en cas de rétractation (si les CGV le précisent) ; à charge du vendeur en cas de défaut.
- Instruments trop grands pour la poste : le client doit être informé **avant l'achat** du coût estimé du retour (obligation d'information si le bien ne peut être renvoyé normalement par la poste).

## 8. Décision D9

- v1 : **grille zone × classe de livraison** administrable dans EasyAdmin + classe `hors_norme` / `retrait_uniquement` + saisie manuelle du numéro de suivi.
- v2 : **Sendcloud** (point relais, étiquettes, suivi, retours).
- Zones de livraison au départ (D11) : **Belgique + Union européenne**, puis Suisse, Royaume-Uni, monde.
