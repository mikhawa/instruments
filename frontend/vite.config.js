import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Cible de l'API Symfony : nginx dans Docker, surchargeable hors conteneur
const apiTarget = process.env.VITE_API_PROXY_TARGET ?? 'http://nginx:80'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    // Détection des modifications fiable sur un volume monté (WSL / Docker)
    watch: { usePolling: true, interval: 300 },
    // Même origine en dev : pas de CORS entre React et l'API Symfony
    proxy: {
      '/api': { target: apiTarget, changeOrigin: true },
    },
  },
})
