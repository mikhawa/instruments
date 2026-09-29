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
