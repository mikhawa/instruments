# 009 — Lien « Administration » dans la barre React

- **Modèle** : Opus
- **Justification** : modification d'interface simple, mais qui implique la configuration de sécurité (partage de session entre pare-feux).
- **Date** : 2026-09-29

## Fichiers modifiés
- `frontend/src/components/Layout.jsx` : lien `<a href="/admin">Administration</a>` affiché uniquement si l'utilisateur a `ROLE_ADMIN` (lien HTML classique : la page est servie par Symfony)
- `frontend/vite.config.js` : proxy `/admin` et `/bundles` vers nginx avec `changeOrigin: false` (redirections Symfony et contrôle CSRF par l'en-tête Origin corrects sur localhost:5173)
- `config/packages/security.yaml` : `context: session_utilisateur` sur les pare-feux `admin` et `main` — une seule session : connexion depuis React ⇒ accès direct à `/admin`, et déconnexion de l'un ⇒ déconnexion de l'autre (remplace la session séparée décrite en 008)
- `README.md` : URL du back-office via le proxy Vite

## Vérification (via http://localhost:5173)
- Anonyme → `/admin` redirige vers `/admin/login` (sur 5173)
- Connexion React admin → `/admin` 200, assets EasyAdmin 200
- Déconnexion React → `/admin` redirige vers la connexion
- Client connecté → `/admin` 403
- Connexion par le formulaire admin → `/api/me` 200 ; déconnexion admin → `/api/me` 401
- `vite build` OK (pas de configuration ESLint dans le projet)

Le contrôle d'accès reste assuré côté serveur (`access_control` + `#[IsGranted('ROLE_ADMIN')]`) : masquer le lien n'est qu'une commodité d'interface.
