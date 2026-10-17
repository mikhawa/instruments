# 007 — Connexion : /api/login, /api/me, /api/logout

- **Modèle** : Opus
- **Justification** : sécurité (authentification par session, anti force brute, redirection ouverte, exposition des données utilisateur).
- **Date** : 2026-09-29

## Fichiers créés / modifiés

### Symfony
- `composer.json` / `composer.lock` : `symfony/rate-limiter` (requis par `login_throttling`)
- `config/packages/security.yaml` : `json_login` (email / password), `logout`, `login_throttling` (5 essais / 15 min)
- `config/packages/framework.yaml` : cookie de session `httponly`, `samesite: lax`, `secure: auto`
- `config/packages/translation.yaml` : `default_locale: fr`, locales fr / en / es (messages de sécurité en français)
- `src/Controller/Api/AuthController.php` : `POST /api/login`, `GET /api/me`, `POST /api/logout`
- `src/EventListener/DeconnexionListener.php` : déconnexion → 204 au lieu d'une redirection
- `src/Entity/Utilisateur.php` : groupe `utilisateur:me` (id, email, prénom, nom, locale, rôles ; jamais le hash)

### React
- `frontend/src/api/client.js` : `envoyerJson()`, `fetchUtilisateurCourant()`
- `frontend/src/loaders.js` : `racineLoader`, `connexionLoader`, `connexionAction`, `deconnexionAction`, `cheminDeRetour()` (chemins internes uniquement)
- `frontend/src/router.jsx` : route racine `id: 'racine'`, routes `/connexion` et `/deconnexion`
- `frontend/src/pages/Connexion.jsx` : formulaire, erreur, état d'envoi, comptes de dev (en dev uniquement)
- `frontend/src/components/Layout.jsx` : « Se connecter » ou nom + « Se déconnecter »
- `frontend/src/components/InstrumentCard.jsx`, `pages/InstrumentDetail.jsx` : mention « Non publié » (vue admin)
- `frontend/src/index.css` : styles barre de compte, formulaire, mention brouillon

### Documentation
- `explication/01-react-et-securite-api.md`, `explication/02-chemins-api-et-connexion-react.md` : connexion désormais en place

## Vérification

curl : mauvais mot de passe → 401 « Identifiants invalides. » ; connexion → 200 + cookie `HttpOnly; SameSite=Lax` ;
`/api/me` → 200 / 401 ; admin PATCH → 200, client PATCH → 403 ; logout → 204 ; `GET /api/logout` → 405 ;
6ᵉ échec → « Trop de tentatives… 15 minutes ».

Playwright : lien « Se connecter » avec `?retour=`, erreur affichée et e-mail conservé, retour sur la fiche après connexion,
session conservée au rechargement, admin voit 15 vignettes dont 1 brouillon, `/connexion` connecté → `/`,
déconnexion → 14 vignettes, `?retour=//exemple-malveillant.test` → `/`.

## Reste à faire

Tests PHPUnit (base `instruments_test`), inscription, voters pour les commandes.
