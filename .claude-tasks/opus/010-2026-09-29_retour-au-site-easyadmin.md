# 010 — Lien « Retour au site » dans EasyAdmin

- **Modèle** : Opus
- **Justification** : petite modification, faite dans la continuité des tâches 008 et 009 (session partagée entre pare-feux).
- **Date** : 2026-09-29

## Fichiers modifiés
- `.env` : nouvelle variable `FRONT_URL` (dev : `http://localhost:5173`)
- `src/Controller/Admin/DashboardController.php` :
  - injection de `FRONT_URL` (`#[Autowire(env: 'FRONT_URL')]`)
  - « Retour au site » en haut du menu latéral (remplace « Voir la boutique » qui pointait vers `/`, en 404 sur le port 8080)
  - « Retour au site » ajouté au menu utilisateur (en haut à droite)
  - suppression d'un `use` inutilisé

## Vérification
- Liens rendus avec `href="http://localhost:5173"`
- Session ouverte sur 8080 toujours valide sur 5173 (`/api/me` → 200) : les cookies ne dépendent pas du port, et les pare-feux partagent le contexte `session_utilisateur` (tâche 009)

## Déploiement
- Préprod / prod : définir `FRONT_URL` dans `.env.local` du VPS (`/` si React et Symfony sont servis sur le même domaine, sinon l'URL complète du site)
