import { envoyerJson } from './client.js'

/**
 * Appels de l'API du panier (cookie « panier » HttpOnly géré par le serveur).
 * Toutes les fonctions renvoient { ok, status, corps } (voir envoyerJson) ;
 * le corps est le panier complet recalculé par le serveur, React ne calcule rien.
 */

const BASE = '/api/panier'

/**
 * Panier courant (vide si aucun).
 */
export function fetchPanier(signal) {
  return envoyerJson(BASE, { method: 'GET', signal })
}

/**
 * Ajoute un instrument : la quantité s'ajoute à celle déjà présente (plafonnée au stock).
 *
 * @param {string} iri ex. '/api/instruments/12'
 * @param {number} [quantite]
 */
export function ajouterLigne(iri, quantite = 1, signal) {
  return envoyerJson(`${BASE}/lignes`, { corps: { instrument: iri, quantite }, signal })
}

/**
 * Remplace la quantité d'une ligne (1 à 10, plafonnée au stock).
 */
export function modifierQuantite(idLigne, quantite, signal) {
  return envoyerJson(`${BASE}/lignes/${idLigne}`, {
    method: 'PATCH',
    corps: { quantite },
    contentType: 'application/merge-patch+json',
    signal,
  })
}

export function retirerLigne(idLigne, signal) {
  return envoyerJson(`${BASE}/lignes/${idLigne}`, { method: 'DELETE', signal })
}

/**
 * Vide le panier (204).
 */
export function viderPanier(signal) {
  return envoyerJson(BASE, { method: 'DELETE', signal })
}
