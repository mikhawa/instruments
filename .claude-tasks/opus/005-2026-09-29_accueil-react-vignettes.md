# 005 — Accueil React : vignettes du catalogue

- **Modèle** : Opus
- **Justification** : conception visuelle et première intégration front / API (pagination, familles de catégories).
- **Date** : 2026-09-29

## Fichiers créés / modifiés

- `frontend/src/api/client.js` : `fetchCollection()` suit la pagination Hydra (`view.next`)
- `frontend/src/lib/format.js` : traduction avec repli fr, prix EUR, nom de pays (Intl), état, disponibilité
- `frontend/src/components/InstrumentCard.jsx` : vignette (plaque, catégorie, nom, état, description, prix TTC, stock)
- `frontend/src/App.jsx` : chargement instruments + catégories, états chargement / erreur / vide
- `frontend/src/index.css` : jetons (clair / sombre), grille `auto-fill`, motifs CSS par famille
- `frontend/src/main.jsx` : polices Alegreya / Alegreya Sans auto-hébergées (@fontsource, RGPD)
- `frontend/index.html` : `lang="fr"`, titre, description
- `frontend/vite.config.js` : proxy `/uploads` vers Symfony (photos à venir)
- Supprimés : `App.css`, `assets/hero.png`, `assets/react.svg`, `assets/vite.svg`

## Design

Encre indigo, laiton pour les mentions d'état, teintes de matériaux par famille (épicéa, roseau, peau).
Sans photo, la plaque de la vignette porte un motif CSS : cordes tendues, trous de jeu, peau de tambour.

## Vérification

- `npm run lint` : 0 avertissement ; `npm run build` : OK
- Captures Playwright : 3 colonnes (1440 px), 1 colonne (390 px), mode sombre lisible
- 14 instruments affichés, brouillon exclu, balafon « Épuisé », pièces uniques signalées
