import { createBrowserRouter, data } from 'react-router'
import Layout from './components/Layout.jsx'
import ErreurRoute from './components/ErreurRoute.jsx'
import { catalogueLoader, instrumentLoader } from './loaders.js'
import Catalogue, { CatalogueAttente } from './pages/Catalogue.jsx'
import InstrumentDetail from './pages/InstrumentDetail.jsx'

export const router = createBrowserRouter([
  {
    path: '/',
    Component: Layout,
    HydrateFallback: CatalogueAttente,
    ErrorBoundary: ErreurRoute,
    children: [
      { index: true, loader: catalogueLoader, Component: Catalogue, ErrorBoundary: ErreurRoute },
      { path: 'instruments/:idSlug', loader: instrumentLoader, Component: InstrumentDetail, ErrorBoundary: ErreurRoute },
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
