import { useState } from 'react'
import { Link, useFetcher } from 'react-router'

const QUANTITE_MAX = 10

/**
 * Ajout au panier depuis la fiche. L'envoi passe par l'action de la route /panier ;
 * React Router recharge ensuite le panier (compteur de l'en-tête).
 * Pas de sélecteur de quantité pour une pièce unique ni pour un dernier exemplaire.
 *
 * @param {{ instrument: object }} props
 */
export default function BoutonAjoutPanier({ instrument }) {
  const fetcher = useFetcher()
  const [quantite, setQuantite] = useState(1)
  const stock = instrument.disponible ?? 0
  const envoiEnCours = fetcher.state !== 'idle'
  const resultat = envoiEnCours ? null : fetcher.data

  if (stock <= 0) {
    return (
      <button type="button" className="bouton fiche__ajout" disabled>
        Épuisé
      </button>
    )
  }

  const choixQuantite = !instrument.pieceUnique && stock > 1 ? Math.min(stock, QUANTITE_MAX) : 1

  return (
    <fetcher.Form method="post" action="/panier" className="ajout">
      <input type="hidden" name="intention" value="ajouter" />
      <input type="hidden" name="instrument" value={instrument['@id'] ?? `/api/instruments/${instrument.id}`} />

      <div className="ajout__ligne">
        {choixQuantite > 1 && (
          <label className="ajout__quantite">
            <span>Quantité</span>
            <select name="quantite" value={quantite} onChange={(evenement) => setQuantite(Number(evenement.target.value))}>
              {Array.from({ length: choixQuantite }, (_, i) => i + 1).map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
          </label>
        )}
        {choixQuantite === 1 && <input type="hidden" name="quantite" value="1" />}

        <button type="submit" className="bouton fiche__ajout" disabled={envoiEnCours}>
          {envoiEnCours ? 'Ajout en cours…' : 'Ajouter au panier'}
        </button>
      </div>

      <div className="ajout__retour" aria-live="polite">
        {resultat?.erreur && (
          <p className="formulaire__erreur" role="alert">
            {resultat.erreur}
          </p>
        )}
        {resultat?.ok && (
          <p className="ajout__confirmation">
            Ajouté au panier.{' '}
            <Link to="/panier" className="lien-bouton">
              Voir le panier
            </Link>
          </p>
        )}
      </div>
    </fetcher.Form>
  )
}
