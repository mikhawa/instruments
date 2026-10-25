import { Link, useFetcher, useRouteLoaderData } from 'react-router'
import LignePanier from '../components/LignePanier.jsx'
import { formaterPrix } from '../lib/format.js'

/**
 * Page /panier : lignes, récapitulatif et accès à la commande.
 * Le panier vient du loader de la route racine, recalculé par le serveur ;
 * les écritures passent par l'action de /panier (panierAction).
 */
export default function Panier() {
  const { panier } = useRouteLoaderData('racine') ?? {}
  const fetcher = useFetcher()
  const envoiEnCours = fetcher.state !== 'idle'
  const lignes = panier?.lignes ?? []
  const titre = <title>Votre panier — Instruments traditionnels</title>

  if (!panier) {
    return (
      <main className="panier">
        {titre}
        <h1 className="panier__titre">Votre panier</h1>
        <p className="formulaire__erreur" role="alert">
          Le panier est momentanément indisponible. Rechargez la page dans un instant.
        </p>
      </main>
    )
  }

  if (lignes.length === 0) {
    return (
      <main className="panier">
        {titre}
        <h1 className="panier__titre">Votre panier</h1>
        <p className="panier__vide">Votre panier est vide.</p>
        <Link to="/" className="lien-retour">
          Découvrir le catalogue
        </Link>
      </main>
    )
  }

  const erreur = envoiEnCours ? null : fetcher.data?.erreur

  function vider() {
    if (window.confirm('Retirer tous les instruments de votre panier ?')) {
      fetcher.submit({ intention: 'vider' }, { method: 'post', action: '/panier' })
    }
  }

  return (
    <main className="panier">
      {titre}
      <h1 className="panier__titre">Votre panier</h1>

      <div className="panier__corps">
        <ul className="panier__lignes">
          {lignes.map((ligne) => (
            <LignePanier key={ligne.id} ligne={ligne} />
          ))}
        </ul>

        <aside className="recap" aria-labelledby="titre-recap">
          <h2 id="titre-recap" className="recap__titre">
            Récapitulatif
          </h2>
          <dl className="recap__liste">
            <div>
              <dt>
                Sous-total ({panier.nombreArticles} {panier.nombreArticles > 1 ? 'articles' : 'article'})
              </dt>
              <dd>{formaterPrix(panier.sousTotalTtc)}</dd>
            </div>
            <div className="recap__detail">
              <dt>dont TVA</dt>
              <dd>{formaterPrix(panier.totalTva)}</dd>
            </div>
            <div>
              <dt>Frais de port</dt>
              <dd>calculés à l'étape suivante</dd>
            </div>
            <div className="recap__total">
              <dt>Total TTC</dt>
              <dd>{formaterPrix(panier.totalTtc)}</dd>
            </div>
          </dl>

          {!panier.commandable && (
            <p className="recap__note" role="status">
              Retirez les instruments indisponibles pour pouvoir commander.
            </p>
          )}
          {erreur && (
            <p className="formulaire__erreur" role="alert">
              {erreur}
            </p>
          )}

          <button type="button" className="bouton recap__commander" disabled>
            Commander
          </button>
          <p className="recap__note">
            {panier.commandable ? 'La commande en ligne sera bientôt disponible.' : 'Commande impossible pour le moment.'}
          </p>

          <button type="button" className="lien-bouton" onClick={vider} disabled={envoiEnCours}>
            Vider le panier
          </button>
        </aside>
      </div>
    </main>
  )
}
