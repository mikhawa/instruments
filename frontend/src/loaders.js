import { data, redirect } from 'react-router'
import { fetchCollection, fetchRessource } from './api/client.js'
import { cheminInstrument } from './lib/format.js'

/**
 * Chargement des données avant l'affichage de chaque route.
 */

export async function catalogueLoader({ request }) {
  return { instruments: await fetchCollection('/api/instruments', request.signal) }
}

/**
 * Charge l'instrument depuis « /instruments/1-oud-turc » ; seul l'identifiant compte,
 * un slug absent ou périmé est redirigé vers l'URL canonique.
 */
export async function instrumentLoader({ params, request }) {
  const id = Number.parseInt(params.idSlug, 10)
  if (!Number.isInteger(id) || id <= 0) {
    throw data(null, { status: 404 })
  }

  const instrument = await fetchRessource(`/api/instruments/${id}`, request.signal)
  const canonique = cheminInstrument(instrument)
  if (new URL(request.url).pathname !== canonique) {
    throw redirect(canonique)
  }

  return { instrument }
}
