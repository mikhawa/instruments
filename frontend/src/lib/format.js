/**
 * Utilitaires d'affichage : traductions, prix, pays, état, disponibilité.
 */

export const LOCALE_PAR_DEFAUT = 'fr'

/**
 * Traduction d'une entité dans la locale demandée, sinon en français, sinon la première disponible.
 */
export function traduction(entite, locale = LOCALE_PAR_DEFAUT) {
  const traductions = entite?.traductions ?? {}

  return traductions[locale] ?? traductions[LOCALE_PAR_DEFAUT] ?? Object.values(traductions)[0] ?? {}
}

/**
 * Montant en centimes → « 1 076,90 € ».
 */
export function formaterPrix(centimes, locale = LOCALE_PAR_DEFAUT) {
  return new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR' }).format(centimes / 100)
}

/**
 * Code ISO 3166-1 → nom du pays dans la langue d'affichage.
 */
export function nomPays(code, locale = LOCALE_PAR_DEFAUT) {
  if (!code) return null
  try {
    return new Intl.DisplayNames([locale], { type: 'region' }).of(code)
  } catch {
    return code
  }
}

const ETATS = {
  neuf: null,
  occasion: 'Occasion',
  ancien: 'Ancien',
  restaure: 'Restauré',
}

/**
 * Mention d'état, avec l'année quand elle est connue (« Ancien, 1888 »).
 * Rien pour un instrument neuf : c'est le cas courant.
 */
export function mentionEtat(instrument) {
  const libelle = ETATS[instrument.etat]
  if (!libelle) return null

  return instrument.anneeFabrication ? `${libelle}, ${instrument.anneeFabrication}` : libelle
}

/**
 * Texte et niveau de disponibilité affichés sur la vignette.
 *
 * @returns {{ texte: string, niveau: 'dispo' | 'dernier' | 'epuise' }}
 */
export function disponibilite(instrument) {
  const quantite = instrument.disponible ?? 0

  if (quantite <= 0) return { texte: 'Épuisé', niveau: 'epuise' }
  if (quantite === 1) {
    return { texte: instrument.pieceUnique ? 'Pièce unique' : 'Dernier exemplaire', niveau: 'dernier' }
  }

  return { texte: `${quantite} en stock`, niveau: 'dispo' }
}

/**
 * Famille racine de l'instrument (slug fr : cordes, vents, percussions), pour le motif de la plaque.
 */
export function familleInstrument(instrument) {
  const categorie = instrument.categorie
  const racine = categorie?.parent ?? categorie

  return racine ? (traduction(racine, 'fr').slug ?? null) : null
}

/**
 * Origine lisible : « Anatolie, Turquie ».
 */
export function origineInstrument(instrument, locale = LOCALE_PAR_DEFAUT) {
  return [instrument.regionOrigine, nomPays(instrument.paysOrigine, locale)].filter(Boolean).join(', ')
}

/**
 * URL de la fiche : /instruments/1-oud-turc (l'identifiant sert à l'API, le slug à la lisibilité).
 */
export function cheminInstrument(instrument, locale = LOCALE_PAR_DEFAUT) {
  const slug = traduction(instrument, locale).slug

  return `/instruments/${instrument.id}${slug ? `-${slug}` : ''}`
}

/**
 * Taux de TVA en points de base → « 21 % ».
 */
export function formaterTauxTva(pointsDeBase, locale = LOCALE_PAR_DEFAUT) {
  return new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 2 }).format(pointsDeBase / 10000)
}

/**
 * Poids en grammes → « 1,8 kg » ou « 250 g ».
 */
export function formaterPoids(grammes, locale = LOCALE_PAR_DEFAUT) {
  if (!grammes) return null
  const format = new Intl.NumberFormat(locale, { maximumFractionDigits: 1 })

  return grammes >= 1000 ? `${format.format(grammes / 1000)} kg` : `${format.format(grammes)} g`
}
