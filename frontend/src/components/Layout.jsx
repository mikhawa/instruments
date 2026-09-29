import { Form, Link, Outlet, ScrollRestoration, useLocation, useNavigation, useRouteLoaderData } from 'react-router'

/**
 * Gabarit commun : barre de marque et compte, contenu de la route,
 * restauration du défilement (retour au catalogue à la position quittée).
 */
export default function Layout() {
  const navigation = useNavigation()
  const { utilisateur } = useRouteLoaderData('racine') ?? {}
  const { pathname, search } = useLocation()
  const surConnexion = pathname === '/connexion'

  return (
    <>
      <header className="barre">
        <Link to="/" className="barre__marque">
          Instruments traditionnels
        </Link>

        <div className="barre__compte">
          {utilisateur ? (
            <>
              <span className="barre__utilisateur">
                {utilisateur.prenom} {utilisateur.nom}
                {utilisateur.roles.includes('ROLE_ADMIN') && <span className="barre__role"> (administrateur)</span>}
              </span>
              <Form method="post" action="/deconnexion">
                <button type="submit" className="lien-bouton">
                  Se déconnecter
                </button>
              </Form>
            </>
          ) : (
            !surConnexion && (
              <Link to={`/connexion?retour=${encodeURIComponent(pathname + search)}`} className="lien-bouton">
                Se connecter
              </Link>
            )
          )}
        </div>
      </header>

      <div className="page" aria-busy={navigation.state === 'loading'}>
        <Outlet />
      </div>

      <ScrollRestoration />
    </>
  )
}
