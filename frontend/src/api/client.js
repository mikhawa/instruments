import { data } from 'react-router'

/**
 * Client de l'API Symfony (API Platform).
 * En dev, /api est relayé vers Symfony par le proxy Vite (même origine).
 */

async function requete(url, signal) {
  const reponse = await fetch(url, {
    headers: { Accept: 'application/ld+json' },
    credentials: 'same-origin',
    signal,
  })

  if (!reponse.ok) {
    // Relayé tel quel à l'ErrorBoundary de la route (404 → « introuvable »)
    throw data(null, { status: reponse.status })
  }

  return reponse.json()
}

/**
 * Récupère un élément unique, ex. '/api/instruments/1'.
 *
 * @param {string} chemin
 * @param {AbortSignal} [signal]
 * @returns {Promise<object>}
 */
export function fetchRessource(chemin, signal) {
  return requete(chemin, signal)
}

/**
 * Récupère tous les éléments d'une collection en suivant la pagination Hydra.
 *
 * @param {string} chemin ex. '/api/instruments'
 * @param {AbortSignal} [signal]
 * @returns {Promise<object[]>}
 */
export async function fetchCollection(chemin, signal) {
  const elements = []
  let url = chemin

  while (url) {
    const page = await requete(url, signal)
    elements.push(...(page.member ?? page['hydra:member'] ?? []))
    const vue = page.view ?? page['hydra:view']
    url = vue?.next ?? vue?.['hydra:next'] ?? null
  }

  return elements
}

/**
 * Envoie un corps JSON (connexion, déconnexion, écritures).
 * Ne lève pas d'exception : renvoie { ok, status, corps } pour laisser l'appelant décider.
 *
 * @param {string} chemin
 * @param {{ method?: string, corps?: object, signal?: AbortSignal }} [options]
 * @returns {Promise<{ ok: boolean, status: number, corps: object | null }>}
 */
export async function envoyerJson(chemin, { method = 'POST', corps, signal } = {}) {
  const reponse = await fetch(chemin, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    credentials: 'same-origin',
    body: corps === undefined ? undefined : JSON.stringify(corps),
    signal,
  })
  let contenu = null
  try {
    contenu = await reponse.json()
  } catch {
    // Réponse vide (204) ou non JSON (page d'erreur du serveur)
  }

  return { ok: reponse.ok, status: reponse.status, corps: contenu }
}

/**
 * Utilisateur connecté, ou null si aucune session n'est ouverte (401).
 *
 * @param {AbortSignal} [signal]
 * @returns {Promise<object | null>}
 */
export async function fetchUtilisateurCourant(signal) {
  const reponse = await fetch('/api/me', {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
    signal,
  })

  if (reponse.status === 401) return null
  if (!reponse.ok) throw data(null, { status: reponse.status })

  return reponse.json()
}
