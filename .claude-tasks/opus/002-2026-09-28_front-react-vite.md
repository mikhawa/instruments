# 002 — Front-end React (Vite) dans Docker

- **Modèle** : Opus
- **Justification** : choix d'architecture front (option 2 retenue : SPA React séparée consommant l'API Symfony, à la place de React + ImportMap qui ne permet pas le JSX).
- **Date** : 2026-09-28

## Fichiers créés / modifiés

- `frontend/` (généré par `npm create vite@latest -- --template react`) : React 19.2, Vite 8.3
- `frontend/vite.config.js` : écoute 0.0.0.0:5173, polling, proxy `/api` → `nginx:80`
- `compose.yaml` : service `node` (node:24-alpine, UID/GID de l'hôte, port 5173)
- `Makefile` : cibles `npm` et `front-build`
- `.dockerignore` : `frontend/node_modules`, `frontend/dist`
- `src/Controller/Api/HealthController.php` : `GET /api/health` (état API + BDD)
- `docs/devops/docker-setup.md` : section front-end
- `CLAUDE.md` : ligne « Frontend » mise à jour

## Vérification

- http://localhost:5173 → HTTP 200, JSX compilé par Vite
- `/api/health` en direct (8080) et via le proxy Vite (5173) → `{"status":"ok","database":"ok"}`
- `npm run build` → `frontend/dist` généré
