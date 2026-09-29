import { useLoaderData } from 'react-router'
import InstrumentCard from '../components/InstrumentCard.jsx'

const VIGNETTES_ATTENTE = 8

function Introduction() {
  return (
    <header className="introduction">
      <h1 className="introduction__titre">Des instruments de tradition, choisis chez ceux qui les fabriquent</h1>
      <p className="introduction__texte">
        Luths, vièles, cornemuses, flûtes et tambours, neufs ou anciens, venus d'ateliers du monde entier.
      </p>
    </header>
  )
}

export default function Catalogue() {
  const { instruments } = useLoaderData()

  return (
    <main className="catalogue">
      <title>Instruments traditionnels</title>
      <Introduction />

      {instruments.length === 0 ? (
        <p className="catalogue__message">Aucun instrument n'est en vente pour le moment. Revenez bientôt.</p>
      ) : (
        <>
          <p className="catalogue__compte">
            {instruments.length} instrument{instruments.length > 1 ? 's' : ''}
          </p>
          <div className="grille">
            {instruments.map((instrument) => (
              <InstrumentCard key={instrument.id} instrument={instrument} />
            ))}
          </div>
        </>
      )}
    </main>
  )
}

/**
 * Affiché au tout premier chargement, avant la réponse de l'API.
 */
export function CatalogueAttente() {
  return (
    <main className="catalogue" aria-busy="true">
      <Introduction />
      <div className="grille">
        {Array.from({ length: VIGNETTES_ATTENTE }, (_, i) => (
          <div key={i} className="vignette vignette--attente" aria-hidden="true" />
        ))}
      </div>
    </main>
  )
}
