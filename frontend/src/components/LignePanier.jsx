import { Link, useFetcher } from 'react-router'
import { cheminInstrumentResume, formaterPrix, mentionEtat, origineInstrument } from '../lib/format.js'

const QUANTITE_MAX = 10

/**
 * Messages affichés pour chaque alerte renvoyée par le serveur.
 */
function messageAlerte(alerte, ligne) {
  switch (alerte) {
    case 'indisponible':
      return "Cet instrument n'est plus disponible. Retirez-le pour pouvoir commander."
    case 'quantite_reduite':
      return `Quantité ramenée à ${ligne.quantite} (stock disponible).`
    case 'prix_modifie':
      return `Le prix a changé depuis l'ajout : ${formaterPrix(ligne.prixUnitaireTtcAjout)} → ${formaterPrix(ligne.prixUnitaireTtc)}.`
    default:
      return null
  }
}

/**
 * Une ligne du panier : visuel, identité de l'instrument, quantité, retrait.
 * La quantité affichée suit immédiatement le choix (interface optimiste),
 * puis le serveur corrige si besoin.
 *
 * @param {{ ligne: object }} props
 */
export default function LignePanier({ ligne }) {
  const fetcher = useFetcher()
  const { instrument } = ligne
  const envoiEnCours = fetcher.state !== 'idle'
  const intention = fetcher.formData?.get('intention')
  const quantiteAffichee = intention === 'modifier' ? Number(fetcher.formData.get('quantite')) : ligne.quantite
  const erreur = envoiEnCours ? null : fetcher.data?.erreur
  const origine = origineInstrument(instrument)
  const etat = mentionEtat(instrument)
  const lien = cheminInstrumentResume(instrument)
  const choixQuantite = !instrument.pieceUnique && ligne.disponible ? Math.min(ligne.quantiteMax, QUANTITE_MAX) : 1

  function changerQuantite(evenement) {
    fetcher.submit(
      { intention: 'modifier', ligne: String(ligne.id), quantite: evenement.target.value },
      { method: 'post', action: '/panier' },
    )
  }

  function retirer() {
    fetcher.submit({ intention: 'retirer', ligne: String(ligne.id) }, { method: 'post', action: '/panier' })
  }

  return (
    <li className={`ligne-panier${ligne.disponible ? '' : ' ligne-panier--indisponible'}`} aria-busy={envoiEnCours}>
      <Link to={lien} className="ligne-panier__visuel" tabIndex={-1} aria-hidden="true">
        {instrument.image ? (
          <img src={instrument.image} alt="" loading="lazy" />
        ) : (
          <span className="ligne-panier__repli">{origine || 'Instrument'}</span>
        )}
      </Link>

      <div className="ligne-panier__infos">
        <h2 className="ligne-panier__nom">
          <Link to={lien}>{instrument.nom}</Link>
        </h2>
        {origine && <p className="ligne-panier__detail">{origine}</p>}
        {etat && <p className="ligne-panier__detail ligne-panier__etat">{etat}</p>}
        <p className="ligne-panier__detail">{formaterPrix(ligne.prixUnitaireTtc)} l'unité, TTC</p>

        {ligne.alertes?.map((alerte) => (
          <p key={alerte} className="ligne-panier__alerte" role="status">
            {messageAlerte(alerte, ligne)}
          </p>
        ))}
        {erreur && (
          <p className="ligne-panier__alerte" role="alert">
            {erreur}
          </p>
        )}
      </div>

      <div className="ligne-panier__actions">
        {ligne.disponible && choixQuantite > 1 ? (
          <label className="ajout__quantite">
            <span>Quantité</span>
            <select value={quantiteAffichee} onChange={changerQuantite} disabled={envoiEnCours}>
              {Array.from({ length: choixQuantite }, (_, i) => i + 1).map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
          </label>
        ) : (
          ligne.disponible && <p className="ligne-panier__detail">Quantité : {ligne.quantite}</p>
        )}

        <p className="ligne-panier__total">{ligne.disponible ? formaterPrix(ligne.totalTtc) : '—'}</p>

        <button
          type="button"
          className="lien-bouton"
          onClick={retirer}
          disabled={envoiEnCours}
          aria-label={`Retirer ${instrument.nom} du panier`}
        >
          Retirer
        </button>
      </div>
    </li>
  )
}
