/**
 * Utilitaires d'affichage des commandes : statuts, dates, délais.
 */

export const STATUTS = {
  en_attente_paiement: 'En attente de paiement',
  payee: 'Payée',
  en_preparation: 'En préparation',
  expediee: 'Expédiée',
  livree: 'Livrée',
  annulee: 'Annulée',
  retournee: 'Retournée',
  remboursee: 'Remboursée',
}

export function libelleStatut(statut) {
  return STATUTS[statut] ?? statut
}

/**
 * Date ISO (UTC) → « 25 octobre 2026 à 14:30 », heure de Bruxelles.
 */
export function formaterDateHeure(iso) {
  if (!iso) return null

  return new Intl.DateTimeFormat('fr-BE', {
    timeZone: 'Europe/Brussels',
    dateStyle: 'long',
    timeStyle: 'short',
  }).format(new Date(iso))
}

/**
 * Date ISO (UTC) → « 25/10/2026 », heure de Bruxelles.
 */
export function formaterDate(iso) {
  if (!iso) return null

  return new Intl.DateTimeFormat('fr-BE', { timeZone: 'Europe/Brussels', dateStyle: 'short' }).format(new Date(iso))
}

/**
 * Date ISO (UTC) → « 14:30 », heure de Bruxelles.
 */
export function formaterHeure(iso) {
  if (!iso) return null

  return new Intl.DateTimeFormat('fr-BE', { timeZone: 'Europe/Brussels', hour: '2-digit', minute: '2-digit' }).format(
    new Date(iso),
  )
}

/**
 * Délai d'une option de livraison : « 2 à 5 jours ouvrables ».
 */
export function formaterDelai(option) {
  const { delaiJoursMin: min, delaiJoursMax: max } = option
  if (min != null && max != null) {
    return min === max ? `${min} ${min > 1 ? 'jours ouvrables' : 'jour ouvrable'}` : `${min} à ${max} jours ouvrables`
  }
  if (min != null) return `à partir de ${min} ${min > 1 ? 'jours ouvrables' : 'jour ouvrable'}`
  if (max != null) return `sous ${max} ${max > 1 ? 'jours ouvrables' : 'jour ouvrable'}`

  return null
}

/**
 * Le jeton de suivi est un UUID : tout autre contenu de l'URL est ignoré.
 */
export function jetonValide(valeur) {
  return typeof valeur === 'string' && /^[0-9a-fA-F-]{32,40}$/.test(valeur) ? valeur : null
}

export const STATUTS_PAIEMENT = {
  ouvert: 'Ouvert',
  en_attente: 'En attente',
  autorise: 'Autorisé',
  paye: 'Payé',
  echoue: 'Refusé',
  annule: 'Annulé',
  expire: 'Expiré',
  rembourse: 'Remboursé',
  partiellement_rembourse: 'Partiellement remboursé',
}

export function libelleStatutPaiement(statut) {
  return STATUTS_PAIEMENT[statut] ?? statut
}

/**
 * Un paiement « en cours » n'est ni abouti ni définitivement échoué.
 */
export function paiementEnCours(paiement) {
  return ['ouvert', 'en_attente', 'autorise'].includes(paiement?.statut)
}

/**
 * Tentative terminée sans que le client ait payé.
 */
export function paiementNonAbouti(paiement) {
  return ['echoue', 'annule', 'expire'].includes(paiement?.statut)
}
