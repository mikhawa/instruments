/**
 * Client de l'API Symfony (API Platform).
 * En dev, /api est relayé vers Symfony par le proxy Vite (même origine).
 */

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
    const reponse = await fetch(url, {
      headers: { Accept: 'application/ld+json' },
      credentials: 'same-origin',
      signal,
    })

    if (!reponse.ok) {
      throw new Error(`${chemin} a répondu ${reponse.status}`)
    }

    const page = await reponse.json()
    elements.push(...(page.member ?? page['hydra:member'] ?? []))
    const vue = page.view ?? page['hydra:view']
    url = vue?.next ?? vue?.['hydra:next'] ?? null
  }

  return elements
}
