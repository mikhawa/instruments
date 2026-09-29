import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
// Polices auto-hébergées (aucune requête vers Google Fonts)
import '@fontsource/alegreya/400.css'
import '@fontsource/alegreya/400-italic.css'
import '@fontsource/alegreya/700.css'
import '@fontsource/alegreya-sans/400.css'
import '@fontsource/alegreya-sans/500.css'
import '@fontsource/alegreya-sans/700.css'
import './index.css'
import { RouterProvider } from 'react-router'
import { router } from './router.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <RouterProvider router={router} />
  </StrictMode>,
)
