import { useCallback, useEffect, useState } from 'react'
import { fetchCollection } from './api/client.js'
import { traduction } from './lib/format.js'
import InstrumentCard from './components/InstrumentCard.jsx'

const VIGNETTES_ATTENTE = 8

/**
 * Associe chaque catégorie à sa famille racine (slug fr : cordes, vents, percussions).
 */
function indexerCategories(categories) {
  return new Map(
    categories.map((categorie) => {
      const racine = categorie.parent ?? categorie
      return [categorie.id, { nom: traduction(categorie).nom, famille: traduction(racine, 'fr').slug ?? null }]
    }),
  )
}

export default function App() {
  const [etat, setEtat] = useState({ statut: 'chargement', instruments: [], categories: new Map() })

  const charger = useCallback((signal) => {
    Promise.all([fetchCollection('/api/instruments', signal), fetchCollection('/api/categories', signal)])
      .then(([instruments, categories]) => {
        setEtat({ statut: 'pret', instruments, categories: indexerCategories(categories) })
      })
      .catch((erreur) => {
        if (erreur.name !== 'AbortError') {
          setEtat((precedent) => ({ ...precedent, statut: 'erreur' }))
        }
      })
  }, [])

  useEffect(() => {
    const controleur = new AbortController()
    charger(controleur.signal)

    return () => controleur.abort()
  }, [charger])

  const reessayer = () => {
    setEtat((precedent) => ({ ...precedent, statut: 'chargement' }))
    charger()
  }

  const { statut, instruments, categories } = etat

  return (
    <>
      <header className="entete">
        <p className="entete__marque">Instruments traditionnels</p>
        <h1 className="entete__titre">Des instruments de tradition, choisis chez ceux qui les fabriquent</h1>
        <p className="entete__intro">
          Luths, vièles, cornemuses, flûtes et tambours, neufs ou anciens, venus d'ateliers du monde entier.
        </p>
      </header>

      <main className="catalogue" aria-busy={statut === 'chargement'}>
        {statut === 'erreur' && (
          <div className="catalogue__message" role="alert">
            <p>Le catalogue n'a pas pu être chargé. Vérifiez votre connexion puis réessayez.</p>
            <button type="button" className="bouton" onClick={reessayer}>
              Réessayer
            </button>
          </div>
        )}

        {statut === 'pret' && instruments.length === 0 && (
          <p className="catalogue__message">Aucun instrument n'est en vente pour le moment. Revenez bientôt.</p>
        )}

        {statut === 'pret' && instruments.length > 0 && (
          <p className="catalogue__compte">
            {instruments.length} instrument{instruments.length > 1 ? 's' : ''}
          </p>
        )}

        <div className="grille">
          {statut === 'chargement' &&
            Array.from({ length: VIGNETTES_ATTENTE }, (_, i) => <div key={i} className="vignette vignette--attente" aria-hidden="true" />)}

          {statut === 'pret' &&
            instruments.map((instrument) => {
              const categorie = categories.get(instrument.categorie?.id)
              return (
                <InstrumentCard
                  key={instrument.id}
                  instrument={instrument}
                  famille={categorie?.famille ?? null}
                  categorie={categorie?.nom ?? traduction(instrument.categorie).nom ?? null}
                />
              )
            })}
        </div>
      </main>
    </>
  )
}
