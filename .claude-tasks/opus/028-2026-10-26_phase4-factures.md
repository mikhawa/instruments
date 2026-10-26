# 028 — Phase 4 (partie 1) : factures, notes de crédit, régime de la marge, remboursements

- **Date** : 2026-10-26
- **Modèle** : Opus
- **Justification** : la tâche 4.1 (numérotation continue sous verrou) est classée Opus dans `prepare/08-feuille-de-route.md` ; 4.2, 4.3 et 4.6 en dépendent directement (même transaction, mêmes montants) et ont été faites dans la foulée.
- **Décision de l'utilisateur** : avancer les factures **sur hypothèses**, sans attendre les réponses du comptable (étape 0.2).

## Hypothèses à valider avec le comptable

- Factures produites par le site (pas par un logiciel comptable, pas de Peppol).
- TVA belge pour toutes les ventes (pas d'OSS).
- Frais de port au taux normal (21 %), sauf commande entièrement sous régime de la marge (ils suivent alors le régime des biens).
- Régime de la marge : prix de vente inchangé pour le client (prix HT saisi × (1 + taux)), aucune TVA visible ; TVA due = (vente − achat) × taux / (1 + taux), gardée sur la ligne pour la comptabilité ; mentions « Régime particulier – … » (article 58, § 4, du Code de la TVA).
- Note de crédit partielle : TVA ventilée au prorata de la facture d'origine.

## Fichiers créés

- `src/Entity/Facture.php`, `SequenceFacture.php`, `src/Repository/FactureRepository.php`, `src/Enum/TypeFacture.php`, `src/Enum/RegimeTva.php`
- `migrations/Version20261026144642.php` (tables `facture` avec CHECK sur les totaux, `sequence_facture` ; colonnes `instrument.regime_tva`, `instrument.prix_achat`, `ligne_commande.regime_tva`, `ligne_commande.tva_marge`)
- `src/Service/Facturation/` : `NumerotationFacture`, `EmissionFacture`, `GenerateurPdfFacture`, `Vendeur`
- `src/Service/Paiement/RemboursementCommande.php`
- `src/EventListener/Workflow/FactureCommandeListener.php`
- `src/Controller/Api/FactureController.php` (PDF pour le client), `src/Controller/Admin/FactureCrudController.php` (lecture seule + PDF)
- `src/ApiResource/Commande/FactureVue.php`
- `templates/factures/facture.html.twig`, `templates/admin/remboursement.html.twig`, `templates/emails/commande/remboursement.html.twig`
- `translations/factures.fr.yaml`, `factures.en.yaml`, `factures.es.yaml`
- `tests/Api/FactureTest.php` (6 tests)

## Fichiers modifiés

- `composer.json`, `composer.lock` : `dompdf/dompdf` 3.1 (et ses dépendances)
- `src/Entity/Instrument.php` (régime, prix d'achat jamais exposé par l'API, validation), `LigneCommande.php` (régime figé, TVA sur la marge), `Commande.php` (factures)
- `src/Service/Panier/CalculateurPanier.php` (aucune TVA visible sous le régime de la marge)
- `src/Service/Commande/NotificationCommande.php` (facture jointe à l'e-mail « Paiement reçu », e-mail de remboursement)
- `src/Controller/Admin/CommandeCrudController.php` (formulaire « Rembourser » ; « Annuler » refusé pour une commande payée, y compris côté serveur), `InstrumentCrudController.php`, `DashboardController.php`
- `src/ApiResource/Commande/CommandeVue.php` (liste des factures), `src/Twig/PrixExtension.php` (filtre `pays`), `src/Command/VerifierConfigurationPaiementCommand.php` (avertissement si le vendeur n'est pas configuré)
- `translations/enums.fr.yaml`, `.env`, `.env.dev`, `.env.test` (`VENDEUR_*`)
- `tests/Api/CommandeTest.php`, `PaiementTest.php` (nettoyage des factures), `tests/Controller/Admin/LivraisonCommandeAdminTest.php` (plus de bouton « Annuler » sur une commande payée)
- Front : `components/DetailCommande.jsx` (liens des factures et notes de crédit), `pages/CommandeConfirmation.jsx`, `CommandeSuivi.jsx`, `index.css`
- Docs : `docs/architecture/database-schema.md`, `docs/devops/vps-preprod.md`, `prepare/08-feuille-de-route.md`

## Vérifications

- `php bin/phpunit` : 85 tests OK. Défaut trouvé et corrigé par le test du formulaire : la case « Annuler aussi la commande » était cochée par défaut.
- `lint:container`, `lint:twig`, `lint:yaml`, `doctrine:schema:validate`, `php-cs-fixer` (fichiers modifiés) : OK.
- Front : lint (0 erreur) et build OK ; parcours Chromium : paiement simulé → lien « Facture F2026-… » sur la confirmation → PDF 200.
- PDF relu visuellement (facture au régime normal).

## Limites connues

- Pas d'OSS, d'autoliquidation (B2B), de facture d'acompte ni de Peppol.
- Les commandes payées avant cette version n'ont pas de facture.
- Une note de crédit est émise même si l'envoi du remboursement au prestataire échoue ensuite (le remboursement reste dû et visible dans Paiements).
- Le PDF au régime de la marge n'a pas été relu visuellement (seulement vérifié par les tests).
