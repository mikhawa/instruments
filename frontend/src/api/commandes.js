import { envoyerJson } from './client.js'

/**
 * Appels de l'API des commandes et de la livraison.
 * Toutes les fonctions renvoient { ok, status, corps } (voir envoyerJson).
 */

/**
 * Options de livraison du panier courant pour un pays (sans pays : retrait seulement).
 */
export function fetchOptionsLivraison(pays, signal) {
  const requete = pays ? `?pays=${encodeURIComponent(pays)}` : ''

  return envoyerJson(`/api/panier/livraison${requete}`, { method: 'GET', signal })
}

/**
 * Crée la commande. La clé d'idempotence protège du double envoi :
 * la même clé renvoie la même commande.
 *
 * @param {object} corps
 * @param {string} cle 8 à 64 caractères [A-Za-z0-9_-]
 */
export async function creerCommande(corps, cle, signal) {
  const reponse = await fetch('/api/commandes', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'Idempotency-Key': cle },
    credentials: 'same-origin',
    body: JSON.stringify(corps),
    signal,
  })
  let contenu = null
  try {
    contenu = await reponse.json()
  } catch {
    // Réponse vide ou non JSON
  }

  return { ok: reponse.ok, status: reponse.status, corps: contenu }
}

/**
 * Commande par son numéro ; le jeton de suivi sert au client sans compte.
 */
export function fetchCommande(numero, jeton, signal) {
  const requete = jeton ? `?jeton=${encodeURIComponent(jeton)}` : ''

  return envoyerJson(`/api/commandes/${encodeURIComponent(numero)}${requete}`, { method: 'GET', signal })
}

/**
 * Commandes du client connecté, plus récentes d'abord (401 sinon).
 */
export function fetchCommandes(signal) {
  return envoyerJson('/api/commandes', { method: 'GET', signal })
}
