import { Link } from 'react-router'

/**
 * Page provisoire des conditions générales de vente.
 */
export default function Cgv() {
  return (
    <main className="commande">
      <title>Conditions générales de vente — Instruments traditionnels</title>
      <h1 className="commande__titre">Conditions générales de vente</h1>
      <p className="commande__intro">
        Les conditions générales de vente définitives seront publiées avant l'ouverture de la vente. Ce texte est
        provisoire.
      </p>
      <Link to="/commande" className="lien-retour">
        Retour à la commande
      </Link>
    </main>
  )
}
