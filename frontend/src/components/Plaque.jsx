import { origineInstrument, traduction } from '../lib/format.js'

/**
 * Visuel d'un instrument : sa première photo, ou à défaut une plaque teintée
 * portant le motif de sa famille et son origine.
 * Le motif est porté par la classe `famille--{cordes|vents|percussions}` du parent.
 *
 * @param {{ instrument: object, className?: string }} props
 */
export default function Plaque({ instrument, className = '' }) {
  const image = instrument.images?.[0]
  const origine = origineInstrument(instrument)

  return (
    <div className={`plaque ${className}`}>
      {image ? (
        <img src={`/uploads/instruments/${image.fichier}`} alt={image.alt ?? traduction(instrument).nom} loading="lazy" />
      ) : (
        origine && <p className="plaque__origine">{origine}</p>
      )}
    </div>
  )
}
