import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Cible de l'API Symfony : nginx dans Docker, surchargeable hors conteneur
const apiTarget = process.env.VITE_API_PROXY_TARGET ?? 'http://nginx:80'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    // En production, le build est copié dans public/ de Symfony :
    // « app/ » évite le conflit avec /assets/ réservé à l'AssetMapper
    assetsDir: 'app',
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    // Détection des modifications fiable sur un volume monté (WSL / Docker)
    watch: { usePolling: true, interval: 300 },
    // Même origine en dev : pas de CORS entre React et l'API Symfony
    proxy: {
      '/api': { target: apiTarget, changeOrigin: true },
      '/uploads': { target: apiTarget, changeOrigin: true },
      // Back-office EasyAdmin et ses assets. Host conservé : Symfony génère
      // ses redirections (connexion, déconnexion) vers localhost:5173
      // et la vérification CSRF compare l'en-tête Origin à ce Host.
      '/admin': { target: apiTarget, changeOrigin: false },
      '/bundles': { target: apiTarget, changeOrigin: false },
    },
  },
})
