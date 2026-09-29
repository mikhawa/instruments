import { disponibilite, formaterPrix, mentionEtat, nomPays, traduction } from '../lib/format.js'

/**
 * Vignette d'un instrument du catalogue.
 * Sans photo, la plaque supérieure affiche un motif propre à la famille
 * (cordes, trous de jeu, peau de tambour) et l'origine de l'instrument.
 *
 * @param {{ instrument: object, famille: string | null, categorie: string | null }} props
 */
export default function InstrumentCard({ instrument, famille, categorie }) {
  const { nom, descriptionCourte } = traduction(instrument)
  const image = instrument.images?.[0]
  const pays = nomPays(instrument.paysOrigine)
  const origine = [instrument.regionOrigine, pays].filter(Boolean).join(', ')
  const etat = mentionEtat(instrument)
  const stock = disponibilite(instrument)

  return (
    <article className={`vignette vignette--${famille ?? 'autre'}`}>
      <div className="vignette__plaque">
        {image ? (
          <img src={`/uploads/instruments/${image.fichier}`} alt={image.alt ?? nom} loading="lazy" />
        ) : (
          origine && <p className="vignette__origine">{origine}</p>
        )}
      </div>

      <div className="vignette__corps">
        {categorie && <p className="vignette__categorie">{categorie}</p>}
        <h2 className="vignette__nom">{nom}</h2>
        {etat && <p className="vignette__etat">{etat}</p>}
        {descriptionCourte && <p className="vignette__description">{descriptionCourte}</p>}
      </div>

      <footer className="vignette__pied">
        <p className="vignette__prix">
          {formaterPrix(instrument.prixTtc)}
          <span className="vignette__ttc"> TTC</span>
        </p>
        <p className={`vignette__stock vignette__stock--${stock.niveau}`}>{stock.texte}</p>
      </footer>
    </article>
  )
}
