import { isRouteErrorResponse, Link, useLocation, useRouteError } from 'react-router'

/**
 * Affichage des erreurs de chargement d'une route (introuvable, API indisponible).
 */
export default function ErreurRoute() {
  const erreur = useRouteError()
  const introuvable = isRouteErrorResponse(erreur) && erreur.status === 404
  const surFiche = useLocation().pathname.startsWith('/instruments/')
  const titreIntrouvable = surFiche ? 'Cet instrument est introuvable' : 'Cette page est introuvable'
  const texteIntrouvable = surFiche
    ? "Il n'existe pas ou n'est plus proposé à la vente."
    : "L'adresse saisie ne correspond à aucune page du site."

  return (
    <main className="erreur" role="alert">
      <title>{introuvable ? 'Page introuvable — Instruments traditionnels' : 'Erreur — Instruments traditionnels'}</title>
      <h1 className="erreur__titre">{introuvable ? titreIntrouvable : 'Le chargement a échoué'}</h1>
      <p>
        {introuvable
          ? texteIntrouvable
          : "Le catalogue ne répond pas pour le moment. Vérifiez votre connexion puis rechargez la page."}
      </p>
      <p className="erreur__actions">
        {!introuvable && (
          <button type="button" className="bouton" onClick={() => window.location.reload()}>
            Recharger la page
          </button>
        )}
        <Link to="/" className="lien-retour">
          Voir tout le catalogue
        </Link>
      </p>
    </main>
  )
}
