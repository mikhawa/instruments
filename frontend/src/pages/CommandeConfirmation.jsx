import { useState } from 'react'
import { Link, useLoaderData } from 'react-router'
import CommandeIntrouvable from '../components/CommandeIntrouvable.jsx'
import ChoixPaiement from '../components/ChoixPaiement.jsx'
import DetailCommande from '../components/DetailCommande.jsx'

/**
 * Page /commande/confirmation/:numero?jeton=… : commande enregistrée, réservation en cours.
 * Elle n'affirme « payée » que si le statut renvoyé par le serveur l'est ; sinon elle propose le paiement.
 */
export default function CommandeConfirmation() {
  const { commande, jeton } = useLoaderData()
  const [copie, setCopie] = useState(false)

  if (!commande) return <CommandeIntrouvable />

  const lienSuivi = `${window.location.origin}/commande/suivi/${encodeURIComponent(commande.numero)}${
    jeton ? `?jeton=${encodeURIComponent(jeton)}` : ''
  }`

  async function copier() {
    try {
      await navigator.clipboard.writeText(lienSuivi)
      setCopie(true)
    } catch {
      // Presse-papiers indisponible : le lien reste sélectionnable dans le champ
    }
  }

  return (
    <main className="commande">
      <title>{`Commande ${commande.numero} — Instruments traditionnels`}</title>
      <h1 className="commande__titre">Votre commande est enregistrée</h1>

      <ChoixPaiement commande={commande} />

      <p className="commande__intro">
        Un e-mail de confirmation a été envoyé à <strong>{commande.email}</strong>.
      </p>

      <DetailCommande commande={commande} />

      <section className="commande__suivi" aria-labelledby="titre-lien-suivi">
        <h2 id="titre-lien-suivi">Suivre cette commande</h2>
        <p>
          Conservez ce lien : il vous permet de suivre la commande sans compte.
        </p>
        <div className="commande__lien">
          <input type="text" readOnly value={lienSuivi} aria-label="Lien de suivi de la commande" onFocus={(e) => e.target.select()} />
          <button type="button" className="bouton" onClick={copier}>
            {copie ? 'Lien copié' : 'Copier le lien'}
          </button>
        </div>
        <p>
          <Link to={`/commande/suivi/${encodeURIComponent(commande.numero)}${jeton ? `?jeton=${encodeURIComponent(jeton)}` : ''}`}>
            Ouvrir la page de suivi
          </Link>
        </p>
      </section>

      <Link to="/" className="lien-retour">
        Retour au catalogue
      </Link>
    </main>
  )
}
