import { Link, useLoaderData } from 'react-router'
import Plaque from '../components/Plaque.jsx'
import {
  disponibilite,
  familleInstrument,
  formaterPoids,
  formaterPrix,
  formaterTauxTva,
  mentionEtat,
  origineInstrument,
  traduction,
} from '../lib/format.js'

const ETATS = { neuf: 'Neuf', occasion: 'Occasion', ancien: 'Ancien', restaure: 'Restauré' }

export default function InstrumentDetail() {
  const { instrument } = useLoaderData()
  const { nom, descriptionCourte, description, histoire, materiaux } = traduction(instrument)
  const categorie = traduction(instrument.categorie).nom
  const origine = origineInstrument(instrument)
  const etat = mentionEtat(instrument)
  const stock = disponibilite(instrument)

  // Fiche technique : seules les lignes renseignées sont affichées
  const fiche = [
    ['Origine', origine],
    ['Facteur', instrument.facteur],
    ['Année de fabrication', instrument.anneeFabrication],
    ['État', ETATS[instrument.etat]],
    ['Matériaux', materiaux],
    ['Dimensions', instrument.dimensions],
    ['Poids', formaterPoids(instrument.poidsGrammes)],
    ['Référence', instrument.reference],
  ].filter(([, valeur]) => valeur)

  return (
    <main className={`fiche famille--${familleInstrument(instrument) ?? 'autre'}`}>
      <title>{`${nom} — Instruments traditionnels`}</title>
      {descriptionCourte && <meta name="description" content={descriptionCourte} />}

      <Link to="/" className="lien-retour">
        Tout le catalogue
      </Link>

      <div className="fiche__haut">
        <Plaque instrument={instrument} className="fiche__plaque" />

        <div className="fiche__resume">
          {categorie && <p className="fiche__categorie">{categorie}</p>}
          <h1 className="fiche__nom">{nom}</h1>
          {etat && <p className="fiche__etat">{etat}</p>}
          {!instrument.published && <p className="brouillon" title="Visible par les administrateurs seulement">Non publié</p>}
          {descriptionCourte && <p className="fiche__accroche">{descriptionCourte}</p>}

          <div className="fiche__achat">
            <p className="fiche__prix">{formaterPrix(instrument.prixTtc)}</p>
            <p className="fiche__taxes">
              TTC, dont TVA {formaterTauxTva(instrument.tauxTva)} ({formaterPrix(instrument.prixHt)} HT)
            </p>
            <p className={`stock stock--${stock.niveau}`}>{stock.texte}</p>
          </div>
        </div>
      </div>

      <div className="fiche__bas">
        <div className="fiche__texte">
          {description && (
            <section>
              <h2 className="fiche__intertitre">Description</h2>
              <p>{description}</p>
            </section>
          )}
          {histoire && (
            <section className="fiche__histoire">
              <h2 className="fiche__intertitre">Histoire</h2>
              <p>{histoire}</p>
            </section>
          )}
        </div>

        <section className="fiche__technique" aria-labelledby="titre-fiche-technique">
          <h2 id="titre-fiche-technique" className="fiche__intertitre">
            Fiche technique
          </h2>
          <dl>
            {fiche.map(([terme, valeur]) => (
              <div key={terme} className="fiche__ligne">
                <dt>{terme}</dt>
                <dd>{valeur}</dd>
              </div>
            ))}
          </dl>
        </section>
      </div>
    </main>
  )
}
