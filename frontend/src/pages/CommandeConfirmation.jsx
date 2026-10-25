import { useState } from 'react'
import { Link, useLoaderData } from 'react-router'
import CommandeIntrouvable from '../components/CommandeIntrouvable.jsx'
import DetailCommande from '../components/DetailCommande.jsx'
import { formaterDateHeure } from '../lib/commande.js'

/**
 * Page /commande/confirmation/:numero?jeton=… : commande enregistrée, réservation en cours.
 * Elle n'affirme jamais que la commande est payée (le paiement en ligne n'existe pas encore).
 */
export default function CommandeConfirmation() {
  const { commande, jeton } = useLoaderData()
  const [copie, setCopie] = useState(false)

  if (!commande) return <CommandeIntrouvable />

  const lienSuivi = `${window.location.origin}/commande/suivi/${encodeURIComponent(commande.numero)}${
    jeton ? `?jeton=${encodeURIComponent(jeton)}` : ''
  }`
  const enAttente = commande.statut === 'en_attente_paiement'

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

      {enAttente ? (
        <p className="commande__alerte" role="status">
          <strong>Elle n'est pas encore payée.</strong> Le paiement en ligne sera disponible prochainement
          {commande.expireAt && <> ; vos instruments sont réservés jusqu'à {formaterDateHeure(commande.expireAt)}</>}.
        </p>
      ) : (
        <p className="commande__intro">
          Statut actuel de la commande : consultez la frise de suivi pour connaître son avancement.
        </p>
      )}

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
