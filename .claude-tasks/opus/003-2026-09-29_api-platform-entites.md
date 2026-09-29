# 003 — API Platform et entités Doctrine

- **Modèle** : Opus
- **Justification** : modélisation du domaine (stocks, commandes), règles de sécurité de l'API et intégrité des données.
- **Date** : 2026-09-29

## Fichiers créés / modifiés

- `composer.json` / `composer.lock` : `api-platform/symfony` 5.0.1 (+ nelmio/cors-bundle)
- `config/packages/api_platform.yaml` : titre, formats JSON + JSON-LD, `stateless: false` (session)
- `config/packages/security.yaml` : fournisseur `utilisateurs` (entité Utilisateur, e-mail)
- `src/Enum/` : `EtatInstrument`, `TypeMouvementStock`, `StatutCommande`
- `src/Entity/Trait/HorodatageTrait.php`
- `src/Entity/` : `Utilisateur`, `Adresse`, `Categorie`, `CategorieTraduction`, `Instrument`,
  `InstrumentTraduction`, `InstrumentImage`, `Stock`, `MouvementStock`, `Commande`, `LigneCommande`
- `src/Repository/` : un repository par entité (`UtilisateurRepository` gère la mise à jour des hash)
- `src/Doctrine/CatalogueVisibleExtension.php` : public = instruments publiés / catégories actives
- `migrations/Version20260929070057.php` : schéma initial + contraintes `CHECK` ajoutées à la main
- `docs/architecture/database-schema.md`, `docs/devops/docker-setup.md`

## Résumé

Schéma conforme à `docs/architecture/database-schema.md`. `Stock` garantit ses invariants
(réserver / libérer / sortir / ajuster) avec verrou optimiste. `Categorie` et `Instrument` sont exposés
(lecture publique, écriture `ROLE_ADMIN`), avec les traductions indexées par locale, `prixTtc` et
`disponible` calculés.

## Vérification

- `doctrine:schema:validate` : mapping correct, base synchronisée
- Instrument publié visible, brouillon → 404 ; POST/DELETE anonymes → 401
- Surréservation refusée par l'entité et par la contrainte SQL `chk_stock_reservee`
- Proxy Vite `/api` → OK ; données de test supprimées après vérification

## Problèmes rencontrés

- Colonne `#[ORM\Version]` : type explicite `integer` requis.
- `stateless: true` (défaut API Platform) incompatible avec le pare-feu à session → erreur 500.
- Propriétés `isXxx` absentes du JSON → renommées `xxx` (colonnes SQL inchangées).
- Cache de métadonnées dev périmé malgré `cache:clear` → `rm -rf var/cache/dev` + redémarrage PHP.
