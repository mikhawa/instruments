import { Link, Outlet, ScrollRestoration, useNavigation } from 'react-router'

/**
 * Gabarit commun : barre de marque, contenu de la route, restauration du défilement
 * (retour au catalogue à la position quittée).
 */
export default function Layout() {
  const navigation = useNavigation()

  return (
    <>
      <header className="barre">
        <Link to="/" className="barre__marque">
          Instruments traditionnels
        </Link>
      </header>

      <div className="page" aria-busy={navigation.state === 'loading'}>
        <Outlet />
      </div>

      <ScrollRestoration />
    </>
  )
}
