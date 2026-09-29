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
