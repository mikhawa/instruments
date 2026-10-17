# 015 — Linter PHP (PHP-CS-Fixer) en local et en CI

- **Modèle utilisé** : Sonnet (vérification par Opus)
- **Justification** : ajout d'outillage de qualité et d'une étape de CI, sans changement métier.

## Fichiers modifiés

- `composer.json`, `composer.lock`, `symfony.lock` : `friendsofphp/php-cs-fixer` en dépendance de dev
- `.php-cs-fixer.dist.php` (créé) : règles `@Symfony` seules, sur `src`, `tests`, `migrations`
- `.gitignore` : `.php-cs-fixer.cache` (recette Flex)
- `Makefile` : cibles `lint` (PHP-CS-Fixer, lints Symfony, oxlint) et `lint-fix`
- `.github/workflows/deploy.yml` : `php-cs-fixer check --diff` en tête de l'étape « Lint » du job `tests-symfony`
- `RACCOURCIS.md` : `make lint` et `make lint-fix`
- `src/**` (19 fichiers) : mise aux normes par `make lint-fix`

## Résumé

- Le reformatage de `src/` est essentiellement la conversion des fins de ligne CRLF en LF
  (cohérent avec `.gitattributes`). Seules autres modifications : un `use` déplacé dans
  `Utilisateur.php` et un point final ajouté à un commentaire de `CatalogueFixtures.php`.
- `make lint` passe. `make test` : aucun test dans le projet (« No tests executed! »).
- Commit `1b58b7c` réalisé par l'utilisateur ; cette fiche a été ajoutée après coup.
