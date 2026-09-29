# instruments

## Accès en développement

| Service | URL |
|---|---|
| Symfony (API) | http://localhost:8080 — documentation de l'API : http://localhost:8080/api/docs |
| React (Vite) | http://localhost:5173 |
| Back-office (EasyAdmin) | http://localhost:8080/admin — compte `ROLE_ADMIN` requis |
| phpMyAdmin | http://localhost:8081 |
| Mailpit | http://localhost:8025 |

Démarrage : `make up` (ou `docker compose up -d --build`) — détails dans `docs/devops/docker-setup.md`.

## Données de développement

`make fixtures` (ou `docker compose exec php php bin/console doctrine:fixtures:load`) vide la base et charge :
15 instruments (fr/en/es) répartis dans 9 catégories, 4 comptes et 5 commandes (livrée, expédiée, payée, en attente de paiement, annulée).

| Compte | Mot de passe | Rôle |
|---|---|---|
| admin@instruments.test | admin | administrateur |
| marie.dubois@example.test | client | cliente (fr, Liège) |
| jan.peeters@example.test | client | client (en, Gent) |
| lucia.garcia@example.test | client | cliente (es, Vigo) |
