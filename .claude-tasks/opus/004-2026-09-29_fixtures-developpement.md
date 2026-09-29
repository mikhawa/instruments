# 004 — Fixtures de développement

- **Modèle** : Opus
- **Justification** : données cohérentes avec les invariants de stock et le cycle de vie des commandes (réservation, sortie, libération journalisées).
- **Date** : 2026-09-29

## Fichiers créés / modifiés

- `composer.json` / `composer.lock` : `doctrine/doctrine-fixtures-bundle` 4.3 (dev)
- `src/DataFixtures/UtilisateurFixtures.php` : 1 admin + 3 clients (BE fr, BE en, ES es) avec adresse
- `src/DataFixtures/CatalogueFixtures.php` : 9 catégories, 15 instruments traduits fr/en/es, stock initial + mouvements d'entrée
- `src/DataFixtures/CommandeFixtures.php` : 5 commandes (livrée, expédiée, payée, en attente, annulée) et leurs effets de stock
- `src/DataFixtures/AppFixtures.php` : supprimé (exemple vide de la recette)
- `Makefile` : cible `fixtures`
- `README.md`, `docs/devops/docker-setup.md` : comptes et commande de chargement

## Cas couverts

Pièces uniques (vielle Pajot 1905, hardingfele 1888), instrument restauré, occasion, brouillon non publié (erhu),
rupture de stock (balafon), stock réservé (gaita, duduk).

## Vérification

- `doctrine:fixtures:load` : OK
- Totaux de commande HT / TVA / TTC corrects ; stock physique = somme des mouvements physiques
- API : 14 instruments publics (brouillon exclu), `disponible` tient compte des réservations
- Hash du mot de passe admin vérifié
