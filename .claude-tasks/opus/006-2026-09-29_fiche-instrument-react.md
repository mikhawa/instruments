# 006 — Fiche détaillée d'un instrument (React Router)

- **Modèle** : Opus
- **Justification** : mise en place du routage (loaders, erreurs, URL canoniques, restauration du défilement) et de la page détail.
- **Date** : 2026-09-29

## Fichiers créés / modifiés

- `frontend/package.json` : `react-router` 8.4 (mode data : `createBrowserRouter`, loaders)
- `frontend/src/router.jsx` : routes `/`, `/instruments/:idSlug`, `*` (404)
- `frontend/src/loaders.js` : `catalogueLoader`, `instrumentLoader` (id → API, slug absent/périmé → redirection canonique)
- `frontend/src/main.jsx` : `RouterProvider`
- `frontend/src/components/Layout.jsx` : barre de marque, `Outlet`, `ScrollRestoration`
- `frontend/src/components/ErreurRoute.jsx` : 404 instrument / page, erreur API avec rechargement
- `frontend/src/components/Plaque.jsx` : visuel partagé (photo ou motif de famille + origine)
- `frontend/src/components/InstrumentCard.jsx` : vignette entièrement cliquable (lien étendu, focus clavier visible)
- `frontend/src/pages/Catalogue.jsx` (ex-`App.jsx`) : données via loader, `CatalogueAttente` au premier chargement
- `frontend/src/pages/InstrumentDetail.jsx` : résumé (prix TTC, TVA, HT, disponibilité), description, histoire, fiche technique
- `frontend/src/api/client.js` : `fetchRessource()`, erreurs HTTP relayées aux ErrorBoundary
- `frontend/src/lib/format.js` : famille, origine, chemin, taux de TVA, poids
- `frontend/src/index.css` : styles fiche, erreur, lien étendu
- `src/Entity/Categorie.php` : `parent` exposé dans `instrument:read` (famille connue sans requête supplémentaire)

## Vérification (Playwright)

- Clic sur la plaque d'une vignette → `/instruments/5-vielle-a-roue-pajot-1905`, titre de page mis à jour
- Retour arrière : position de défilement restaurée (664 px → 664 px)
- Navigation clavier : Tab + Entrée ouvre la fiche
- `/instruments/1` et `/instruments/1-mauvais-slug` → redirigés vers `/instruments/1-oud-turc`
- Brouillon `/instruments/7-erhu`, `/instruments/999` → « Cet instrument est introuvable » ; URL inconnue → « Cette page est introuvable »
- `npm run lint` : 0 avertissement ; `npm run build` : OK ; captures bureau, mobile, sombre vérifiées

## À prévoir

En production, le serveur web devra renvoyer `index.html` pour toute URL non-API (fallback SPA).
