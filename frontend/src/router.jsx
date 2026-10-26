import { createBrowserRouter, data, redirect } from 'react-router'
import Layout from './components/Layout.jsx'
import ErreurRoute from './components/ErreurRoute.jsx'
import {
  catalogueLoader,
  connexionAction,
  connexionLoader,
  deconnexionAction,
  instrumentLoader,
  panierAction,
  racineLoader,
} from './loaders.js'
import {
  commandeAction,
  commandeLoader,
  detailCommandeLoader,
  mesCommandesLoader,
  paiementAction,
  simulationAction,
  simulationLoader,
} from './loadersCommande.js'
import Catalogue, { CatalogueAttente } from './pages/Catalogue.jsx'
import Cgv from './pages/Cgv.jsx'
import Commande from './pages/Commande.jsx'
import CommandeConfirmation from './pages/CommandeConfirmation.jsx'
import CommandeSuivi from './pages/CommandeSuivi.jsx'
import Connexion from './pages/Connexion.jsx'
import InstrumentDetail from './pages/InstrumentDetail.jsx'
import MesCommandes from './pages/MesCommandes.jsx'
import Panier from './pages/Panier.jsx'
import PaiementSimulation from './pages/PaiementSimulation.jsx'

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
      { path: 'panier', action: panierAction, Component: Panier, ErrorBoundary: ErreurRoute },
      { path: 'commande', loader: commandeLoader, action: commandeAction, Component: Commande, ErrorBoundary: ErreurRoute },
      {
        path: 'commande/confirmation/:numero',
        loader: detailCommandeLoader,
        action: paiementAction,
        Component: CommandeConfirmation,
        ErrorBoundary: ErreurRoute,
      },
      {
        path: 'commande/suivi/:numero',
        loader: detailCommandeLoader,
        action: paiementAction,
        Component: CommandeSuivi,
        ErrorBoundary: ErreurRoute,
      },
      // Simulation de paiement (dev/test) : l'API répond 404 en production
      { path: 'paiement/simulation/:id', loader: simulationLoader, action: simulationAction, Component: PaiementSimulation, ErrorBoundary: ErreurRoute },
      { path: 'compte/commandes', loader: mesCommandesLoader, Component: MesCommandes, ErrorBoundary: ErreurRoute },
      { path: 'cgv', Component: Cgv, ErrorBoundary: ErreurRoute },
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
