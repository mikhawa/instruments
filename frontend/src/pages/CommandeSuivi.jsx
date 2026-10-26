import { Link, useLoaderData, useRouteLoaderData } from 'react-router'
import CommandeIntrouvable from '../components/CommandeIntrouvable.jsx'
import ChoixPaiement from '../components/ChoixPaiement.jsx'
import DetailCommande from '../components/DetailCommande.jsx'

/**
 * Page /commande/suivi/:numero (lien des e-mails) : détail et frise du statut.
 * Le jeton de l'URL remplace la connexion ; un client connecté propriétaire n'en a pas besoin.
 */
export default function CommandeSuivi() {
  const { commande, jeton } = useLoaderData()
  const { utilisateur } = useRouteLoaderData('racine') ?? {}

  if (!commande) return <CommandeIntrouvable />

  return (
    <main className="commande">
      <title>{`Suivi de la commande ${commande.numero} — Instruments traditionnels`}</title>
      <h1 className="commande__titre">Suivi de la commande {commande.numero}</h1>

      {commande.statut === 'en_attente_paiement' && <ChoixPaiement commande={commande} />}

      <DetailCommande commande={commande} jeton={jeton} frise />

      {utilisateur && (
        <Link to="/compte/commandes" className="lien-retour">
          Toutes mes commandes
        </Link>
      )}
    </main>
  )
}
