# 023 — Phase 0 : workflow de commande, Scheduler et worker Messenger

- **Modèle** : Sonnet
- **Justification** : configuration de services (Workflow, Scheduler), test PHPUnit, documentation de déploiement et script shell ; pas de décision d'architecture nouvelle (tâches 0.4 et 0.5 de `prepare/08-feuille-de-route.md`).

## Résumé

**0.4 — Paquets et worker**
- `composer require symfony/workflow symfony/scheduler` ; la recette Scheduler a créé `src/Schedule.php`, supprimé au profit de `src/Scheduler/ProgrammeTaches.php` (`#[AsSchedule('default')]`, `stateful` via le cache, aucun message récurrent pour l'instant ; commentaire listant les tâches à venir). `debug:scheduler` fonctionne, le transport `scheduler_default` existe.
- Constat : `MESSENGER_TRANSPORT_DSN=doctrine://...` et les e-mails sont routés en `async`, mais aucun worker ne tourne sur le VPS. Nouvelle section 6 de `docs/devops/vps-preprod.md` : cron chaque minute, `flock -n`, `messenger:consume async scheduler_default`, chemin via `current/`, journal dans `shared/var/log`.
- `deploy/activer-release.sh` : `messenger:stop-workers` après la bascule (non bloquant).
- `Makefile` : cible `worker`.

**0.5 — Workflow de commande**
- `config/packages/workflow.yaml` : state_machine `commande` (8 places, 8 transitions, audit_trail).
- Cas `Remboursee` ajouté à `StatutCommande`, à `translations/enums.fr.yaml`, au diagramme et au texte de `docs/architecture/database-schema.md`, et à la couleur de badge de l'admin.
- `MethodMarkingStore` de Symfony 7.4 gère nativement les `BackedEnum` (getter et setter typé) : `Commande::getStatut()/setStatut()` utilisés tels quels, aucune méthode dédiée.
- Test `tests/Workflow/CommandeWorkflowTest.php` (marquage initial, parcours complet, expiration, annulations, transitions interdites via DataProvider).

## Fichiers modifiés

- `composer.json`, `composer.lock`, `symfony.lock`, `config/reference.php` (recettes Flex)
- `config/packages/workflow.yaml` (nouveau)
- `src/Scheduler/ProgrammeTaches.php` (nouveau)
- `src/Enum/StatutCommande.php`, `translations/enums.fr.yaml`
- `src/Controller/Admin/CommandeCrudController.php` (couleur du badge `Remboursee`)
- `tests/Workflow/CommandeWorkflowTest.php` (nouveau)
- `Makefile`, `deploy/activer-release.sh`
- `docs/devops/vps-preprod.md`, `docs/architecture/database-schema.md`

## Vérification

- `make lint` : OK ; `php bin/phpunit` : OK (16 tests, 61 assertions).
- `doctrine:schema:update --dump-sql` : aucun changement (colonne VARCHAR, pas de migration).

## Relecture (Opus)

- Garde ajoutée sur `rembourser` (`subject.getPaidAt() !== null`) dans `config/packages/workflow.yaml` : une commande expirée sans paiement ne peut pas passer à `remboursee`.
- `tests/Workflow/CommandeWorkflowTest.php` : le test « expiration puis remboursement » est remplacé par « expirée sans paiement non remboursable » + « annulation après paiement puis remboursement » ; le parcours complet renseigne `paidAt` après `payer`.
- Résultat : 17 tests OK, php-cs-fixer OK.
