# 008 — Back-office EasyAdmin : installation et premiers CRUD

- **Modèle** : Opus
- **Justification** : tâche de niveau Sonnet (CRUD) mais qui touche la sécurité (nouveau pare-feu, CSRF, contrôle d'accès) et les invariants métier (stock, statuts de commande) ; réalisée dans la continuité de l'analyse faite par Opus.
- **Date** : 2026-09-29

## Fichiers créés / modifiés

### Dépendances
- `composer.json` / `composer.lock` : `easycorp/easyadmin-bundle` ^5.6 (+ `symfony/ux-twig-component`, `twig/html-extra`)
- `config/routes/easyadmin.yaml`, `config/packages/twig_component.yaml` : recettes Flex

### Sécurité
- `config/packages/security.yaml` : pare-feu `admin` (`^/admin`, `form_login` + CSRF, `logout`, `login_throttling` 5 essais / 15 min), placé avant `main` ; `access_control` : `/admin/login` public, `/admin` → `ROLE_ADMIN`. Session du back-office indépendante de celle de l'API.

### Back-office
- `src/Controller/Admin/DashboardController.php` : `/admin`, menu, tableau de bord (instruments, catégories, stocks sous seuil, commandes à traiter), `#[IsGranted('ROLE_ADMIN')]`
- `src/Controller/Admin/SecurityController.php` : `/admin/login` (gabarit EasyAdmin), `/admin/logout`
- `src/Controller/Admin/InstrumentCrudController.php` : onglets Général / Prix / Origine / Traductions / Stock ; prix en centimes (`MoneyField`) ; stock en lecture seule ; **suppression désactivée** (dépublier à la place)
- `src/Controller/Admin/CategorieCrudController.php` : traductions imbriquées ; suppression uniquement si la catégorie est vide (masquée + garde-fou serveur)
- `src/Controller/Admin/CommandeCrudController.php` : consultation (badges de statut, lignes, adresses, dates) ; seul le n° de suivi est modifiable ; création et suppression désactivées
- `src/Form/Admin/AbstractTraductionType.php`, `CategorieTraductionType.php`, `InstrumentTraductionType.php` : langue parmi `enabled_locales`, slug généré depuis le nom s'il est vide
- `templates/admin/dashboard.html.twig`

### Domaine
- `src/Entity/Instrument.php`, `Categorie.php` : `getNom(locale)` et `__toString()`
- `src/Entity/Utilisateur.php`, `LigneCommande.php` : `__toString()`
- `src/Enum/EtatInstrument.php`, `StatutCommande.php` : `TranslatableInterface` (libellés lisibles) — la sérialisation API reste la valeur brute (`restaure`)
- `translations/enums.fr.yaml` : libellés des énumérations
- `src/Repository/StockRepository.php` : `compterSousSeuilAlerte()`
- `src/Repository/CommandeRepository.php` : `compterParStatuts()`

### Documentation
- `README.md` : URL du back-office

## Vérification
- `lint:container`, `lint:yaml`, `lint:twig` OK
- Anonyme → `/admin` redirige vers `/admin/login` ; client connecté → 403 ; admin → 200 sur toutes les pages
- Création de catégorie (slugs générés), doublon de slug refusé (422), création d'instrument (stock créé, référence en majuscules), modification du prix, saisie du n° de suivi : vérifiés en base puis données de test supprimées
- Suppression d'instrument et création de commande → 403
- `/api/login` toujours 200 ; `/api/instruments/{id}` renvoie toujours `etat: "restaure"`

## Reste à faire
- Changements de statut de commande via Symfony Workflow (actions dédiées, réservation / sortie de stock)
- Mouvements de stock depuis le back-office (service métier + `MouvementStock`)
- Upload des images (`InstrumentImage`)
- Traductions `enums.en.yaml` / `enums.es.yaml`
- Tests fonctionnels du back-office (aucun test PHPUnit n'existe encore)
