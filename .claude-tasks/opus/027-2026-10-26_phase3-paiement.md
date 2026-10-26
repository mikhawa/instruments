# 027 — Phase 3 : paiement (Mollie, virement SEPA, simulation)

- **Date** : 2026-10-26
- **Modèle** : Opus (back-end, revue), Sonnet en sous-agent (front React)
- **Justification** : les tâches 3.3 (webhooks, idempotence, vérification du montant) et 3.4 (paiement après expiration) sont classées Opus dans `prepare/08-feuille-de-route.md` (argent, concurrence, sécurité) ; le reste du back-end a été fait dans la foulée pour rester cohérent. Le front, indépendant une fois l'API figée, a été délégué à Sonnet puis relu (diff, captures, contrôle du panier).

## Fichiers créés

**Back-end**
- `src/Entity/Paiement.php`, `Remboursement.php`, `EvenementWebhook.php` ; `src/Repository/PaiementRepository.php`, `RemboursementRepository.php`
- `src/Enum/StatutPaiement.php`, `PrestatairePaiement.php`, `MotifAnnulation.php`
- `migrations/Version20261026140218.php` (tables `paiement`, `remboursement`, `evenement_webhook` avec CHECK sur les montants ; `commande.motif_annulation`)
- `src/Service/Paiement/` : `PrestatairePaiementInterface`, `PaiementCree`, `EtatPaiement`, `Prestataires`, `CreationPaiement`, `EncaissementPaiement`, `SynchronisationPaiement`, `RemboursementPaiement`, `PrestataireIndisponibleException`, `PaiementRefuseException`, `Mollie/MolliePrestataire`, `Simulation/SimulationPrestataire`, `Virement/CommunicationStructuree`, `Virement/CoordonneesVirement`
- `src/ApiResource/Paiement/` : `DemandePaiement`, `PaiementVue`, `VirementVue` ; `src/State/Paiement/DemandePaiementProcessor.php`, `VerificationPaiementProcessor.php` ; `src/State/Commande/CommandeDuClient.php`, `VueCommande.php`
- `src/Controller/Api/WebhookPaiementController.php`, `SimulationPaiementController.php` ; `src/Controller/Admin/PaiementCrudController.php`
- `src/Message/TraiterNotificationPaiement.php`, `src/MessageHandler/TraiterNotificationPaiementHandler.php`, `src/Scheduler/Message/SynchroniserPaiements.php`, `src/Scheduler/Handler/SynchroniserPaiementsHandler.php`
- `src/Command/VerifierConfigurationPaiementCommand.php`
- `src/Doctrine/Type/DateTimeImmutableUtcType.php`
- `templates/emails/commande/payee.html.twig`, `remboursee.html.twig`, `virement.html.twig`
- `tests/Api/PaiementTest.php` (14 tests), `tests/Service/Paiement/MolliePrestataireTest.php`, `CommunicationStructureeTest.php`

**Front-end** (sous-agent Sonnet)
- `frontend/src/components/ChoixPaiement.jsx`, `BlocVirement.jsx`, `frontend/src/pages/PaiementSimulation.jsx` ; dépendance `qrcode` 1.5.4 (version exacte, chargée à la demande)

## Fichiers modifiés

- `src/Entity/Commande.php` (paiements, motif d'annulation, `__toString`), `src/ApiResource/Commande/CommandeVue.php` (opérations de paiement, `moyensPaiement`, `paiement`), `src/State/Commande/CommandeProvider.php`, `CreationCommandeProcessor.php`
- `config/packages/workflow.yaml` (transition `payer_apres_expiration`), `src/EventListener/Workflow/HorodatageCommandeListener.php` (motif d'annulation), `StockCommandeListener.php` (nouvelle réservation)
- `src/Service/Commande/NotificationCommande.php` (e-mails payée, remboursée, virement), `src/Controller/Admin/CommandeCrudController.php` (paiements, action « Virement reçu »), `DashboardController.php` (menu Paiements)
- `src/Scheduler/ProgrammeTaches.php` (synchronisation toutes les 15 min), `config/packages/messenger.yaml` (routage async ; `in-memory://` en test), `rate_limiter.yaml` (3 limiteurs), `monolog.yaml` (canal `paiement`), `doctrine.yaml` (type UTC), `config/services.yaml` (`app.virement_seuil`)
- `.env` (`MOLLIE_API_KEY`, `VIREMENT_*`), `.env.dev`, `.env.test` (IBAN d'exemple)
- `deploy/activer-release.sh` et `.github/workflows/deploy.yml` (contrôle de la clé Mollie avant les migrations)
- Front : `api/commandes.js`, `loadersCommande.js`, `router.jsx`, `lib/commande.js`, `pages/Commande.jsx`, `CommandeConfirmation.jsx`, `CommandeSuivi.jsx`, `index.css`, `package.json`, `package-lock.json`
- Docs : `docs/architecture/database-schema.md`, `docs/devops/vps-preprod.md`, `prepare/08-feuille-de-route.md`

## Résumé

- **Paiement en ligne** : `POST /api/commandes/{numero}/paiements` (montant de la commande, jamais du client) crée le paiement chez le prestataire et renvoie sa page ; la page ouverte est réutilisée 10 min. Retour du client : `POST …/verification-paiement` relit le statut chez le prestataire.
- **Notification Mollie** : `POST /api/webhooks/mollie` enregistre l'événement et répond 200 ; le worker relit le statut chez Mollie (pas de signature : la relecture garantit l'authenticité). Identifiant inconnu : 200 sans rien faire.
- **Encaissement** (`EncaissementPaiement`, transaction, verrou commande puis paiement) : montant et devise vérifiés ; `payer` ; reprise d'une commande expirée si le stock suffit, sinon remboursement intégral ; paiement en double remboursé ; rejeu sans effet.
- **Virement** : communication structurée belge (modulo 97), QR code EPC, réservation prolongée à 5 jours ouvrables, à partir de 500 € ; validation par l'administrateur.
- **Simulation** : faux prestataire quand `MOLLIE_API_KEY` est vide, refusé en production ; page React `/paiement/simulation/{id}`.
- **Bug corrigé (antérieur)** : dates stockées en UTC mais relues dans le fuseau de PHP (`Europe/Brussels`) → décalage d'une heure ou deux sur `expireAt` et les dates de l'API. Nouveau type Doctrine `DateTimeImmutableUtcType`.

## Vérifications

- `php bin/phpunit` : 79 tests OK (53 existants + 26 nouveaux). La base `instruments_test` n'existait pas dans le conteneur MariaDB : créée et autorisée pour l'utilisateur `app` (dev local).
- `lint:container`, `lint:twig`, `lint:yaml`, `doctrine:schema:validate` : OK. `php-cs-fixer` : nouveaux fichiers conformes (10 anciens fichiers restent signalés pour leurs fins de ligne CRLF, non modifiés).
- Front : `npm run lint` (0 erreur) et `vite build` OK. Parcours joués dans Chromium (Playwright) : paiement < 500 € accepté, virement avec QR code, paiement en ligne refusé puis nouvel essai, attente de la banque, mobile 375 px.
- Parcours API testé avec curl/Python sur l'environnement de dev.

## Limites connues

- **Aucun appel réel à Mollie** : pas de compte ni de clé de test. À valider en préprod (paiement Bancontact et carte de test, notification, remboursement).
- Notification Mollie impossible en local (adresse non publique) : le retour du client et la synchronisation toutes les 15 min prennent le relais.
- Le remboursement d'un virement (doublon ou commande annulée) reste manuel.
- Une commande payée puis annulée dans le back-office n'est pas remboursée automatiquement (phase 4.3).
- Non testés dans le navigateur : délai d'attente de 60 s dépassé, erreurs 409/429/503 de l'action de paiement, états remboursée/annulée, thème sombre.
