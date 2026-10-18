# 020 — Liens « Retour au site » et « Déconnexion » du back-office en production

- **Modèle** : Opus
- **Justification** : bug de sécurité/authentification (pare-feu à contexte partagé) combiné à la configuration d'environnement.

## Symptômes (https://instruments.michaeljpitz.com/admin)

- « Retour au site » menait à `http://localhost:5173/`.
- « Déconnexion » menait à `/api/logout` (route POST uniquement → erreur).

## Causes

1. `FRONT_URL` valait `http://localhost:5173` dans `.env` (versionné) : sans `FRONT_URL`
   dans `shared/.env.local` du serveur, la valeur de dev s'appliquait en production.
2. `MenuItem::linkToLogout()` utilise `LogoutUrlGenerator::getLogoutPath()` sans pare-feu :
   il prend celui du jeton. Avec la session partagée (`context: session_utilisateur`), un admin
   connecté depuis React porte un jeton du pare-feu `main` → `/api/logout`.

## Corrections

- `.env` : `FRONT_URL=/` par défaut ; `.env.dev` : `FRONT_URL=http://localhost:5173`.
- `DashboardController` : lien de déconnexion généré pour le pare-feu `admin`
  (`/admin/logout`, GET), dans le menu principal et le menu utilisateur (qui remplace le lien
  par défaut d'EasyAdmin). La session étant invalidée, l'utilisateur est aussi déconnecté côté React.

## Fichiers modifiés

- `.env`
- `.env.dev`
- `src/Controller/Admin/DashboardController.php`

## Vérification (dev)

Connexion via `POST /api/login` puis `GET /admin` : les liens pointent vers `/admin/logout` ;
après déconnexion, `/admin` redirige vers `/admin/login` et `/api/me` renvoie 401.
