import { Link, useLoaderData } from 'react-router'
import { formaterDate, libelleStatut } from '../lib/commande.js'
import { formaterPrix } from '../lib/format.js'

/**
 * Page /compte/commandes : commandes du client connecté, plus récentes d'abord.
 */
export default function MesCommandes() {
  const { commandes } = useLoaderData()

  return (
    <main className="commande">
      <title>Mes commandes — Instruments traditionnels</title>
      <h1 className="commande__titre">Mes commandes</h1>

      {commandes.length === 0 ? (
        <>
          <p className="commande__intro">Vous n'avez pas encore passé de commande.</p>
          <Link to="/" className="lien-retour">
            Découvrir le catalogue
          </Link>
        </>
      ) : (
        <ul className="liste-commandes">
          {commandes.map((commande) => (
            <li key={commande.numero} className="liste-commandes__item">
              <div>
                <Link to={`/commande/suivi/${encodeURIComponent(commande.numero)}`} className="liste-commandes__numero">
                  {commande.numero}
                </Link>
                <p className="liste-commandes__date">Passée le {formaterDate(commande.createdAt)}</p>
              </div>
              <span className={`statut statut--${commande.statut}`}>{libelleStatut(commande.statut)}</span>
              <span className="liste-commandes__total">{formaterPrix(commande.totalTtc)}</span>
              <Link to={`/commande/suivi/${encodeURIComponent(commande.numero)}`}>Voir le suivi</Link>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}
