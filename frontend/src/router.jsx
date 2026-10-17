import { createBrowserRouter, data, redirect } from 'react-router'
import Layout from './components/Layout.jsx'
import ErreurRoute from './components/ErreurRoute.jsx'
import {
  catalogueLoader,
  connexionAction,
  connexionLoader,
  deconnexionAction,
  instrumentLoader,
  racineLoader,
} from './loaders.js'
import Catalogue, { CatalogueAttente } from './pages/Catalogue.jsx'
import Connexion from './pages/Connexion.jsx'
import InstrumentDetail from './pages/InstrumentDetail.jsx'

export const router = createBrowserRouter([
  {
    id: 'racine',
    path: '/',
    loader: racineLoader,
    Component: Layout,
    HydrateFallback: CatalogueAttente,
    ErrorBoundary: ErreurRoute,
    children: [
      { index: true, loader: catalogueLoader, Component: Catalogue, ErrorBoundary: ErreurRoute },
      { path: 'instruments/:idSlug', loader: instrumentLoader, Component: InstrumentDetail, ErrorBoundary: ErreurRoute },
      { path: 'connexion', loader: connexionLoader, action: connexionAction, Component: Connexion, ErrorBoundary: ErreurRoute },
      // Déconnexion : uniquement par formulaire POST (un simple lien ne déconnecte pas)
      { path: 'deconnexion', action: deconnexionAction, loader: () => redirect('/') },
      {
        path: '*',
        loader: () => {
          throw data(null, { status: 404 })
        },
        ErrorBoundary: ErreurRoute,
      },
    ],
  },
])
