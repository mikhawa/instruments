# instruments

## Accès en développement

| Service | URL |
|---|---|
| Symfony (API) | http://localhost:8080 — documentation de l'API : http://localhost:8080/api/docs |
| React (Vite) | http://localhost:5173 |
| Back-office (EasyAdmin) | http://localhost:8080/admin (ou http://localhost:5173/admin via le proxy Vite) — compte `ROLE_ADMIN` requis, lien « Administration » dans la barre du site |
| phpMyAdmin | http://localhost:8081 |
| Mailpit | http://localhost:8025 |

Après installation de `sudo apt install make`

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

## Déploiement

GitHub Actions (`.github/workflows/deploy.yml`) : push sur `main` → préprod, tag `v*` → production.
Préparation du VPS CyberPanel, secrets GitHub et retour arrière : `docs/devops/vps-preprod.md`.

copier le résultat dans public/. C'est plus lent et plus fragile, et cela fait doublon avec la CI.

result: React est compilé par la CI (npm run build puis copie de frontend/dist/ dans public/) à chaque push sur main ; en attendant, tu peux compiler en local et envoyer frontend/dist/ par scp
dans <DEPLOY_PATH>/current/public/. 

        php bin/console assets:install public
