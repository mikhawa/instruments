import { Link } from 'react-router'
import Plaque from './Plaque.jsx'
import { cheminInstrument, disponibilite, familleInstrument, formaterPrix, mentionEtat, traduction } from '../lib/format.js'

/**
 * Vignette d'un instrument du catalogue ; toute la vignette mène à la fiche détaillée.
 * Le lien est porté par le nom (lecteurs d'écran) et étendu à la vignette en CSS.
 *
 * @param {{ instrument: object }} props
 */
export default function InstrumentCard({ instrument }) {
  const { nom, descriptionCourte } = traduction(instrument)
  const categorie = traduction(instrument.categorie).nom
  const etat = mentionEtat(instrument)
  const stock = disponibilite(instrument)

  return (
    <article className={`vignette famille--${familleInstrument(instrument) ?? 'autre'}`}>
      <Plaque instrument={instrument} className="vignette__plaque" />

      <div className="vignette__corps">
        {categorie && <p className="vignette__categorie">{categorie}</p>}
        <h2 className="vignette__nom">
          <Link to={cheminInstrument(instrument)} className="vignette__lien">
            {nom}
          </Link>
        </h2>
        {etat && <p className="vignette__etat">{etat}</p>}
        {!instrument.published && <p className="brouillon" title="Visible par les administrateurs seulement">Non publié</p>}
        {descriptionCourte && <p className="vignette__description">{descriptionCourte}</p>}
      </div>

      <footer className="vignette__pied">
        <p className="vignette__prix">
          {formaterPrix(instrument.prixTtc)}
          <span className="vignette__ttc"> TTC</span>
        </p>
        <p className={`stock stock--${stock.niveau}`}>{stock.texte}</p>
      </footer>
    </article>
  )
}
