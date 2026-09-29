import { data, redirect } from 'react-router'
import { envoyerJson, fetchCollection, fetchRessource, fetchUtilisateurCourant } from './api/client.js'
import { cheminInstrument } from './lib/format.js'

/**
 * Chargement des données (loaders) et envois de formulaires (actions) de chaque route.
 * Après chaque action, React Router relance les loaders : l'en-tête se met à jour seul.
 */

/**
 * Route racine : utilisateur connecté (ou null), disponible partout via useRouteLoaderData('racine').
 */
export async function racineLoader({ request }) {
  return { utilisateur: await fetchUtilisateurCourant(request.signal) }
}

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

/**
 * Page de retour après connexion : chemin interne uniquement (pas de redirection vers un autre site).
 */
function cheminDeRetour(url) {
  const retour = new URL(url).searchParams.get('retour')

  return retour && retour.startsWith('/') && !retour.startsWith('//') ? retour : '/'
}

/**
 * Déjà connecté : inutile d'afficher le formulaire.
 */
export async function connexionLoader({ request }) {
  const utilisateur = await fetchUtilisateurCourant(request.signal)
  if (utilisateur) {
    throw redirect(cheminDeRetour(request.url))
  }

  return null
}

export async function connexionAction({ request }) {
  const formulaire = await request.formData()
  const email = String(formulaire.get('email') ?? '').trim()
  const password = String(formulaire.get('password') ?? '')

  if (!email || !password) {
    return { erreur: 'Saisissez votre adresse e-mail et votre mot de passe.', email }
  }

  const reponse = await envoyerJson('/api/login', { corps: { email, password }, signal: request.signal })
  if (reponse.ok) {
    return redirect(cheminDeRetour(request.url))
  }

  // 401 : identifiants invalides ou trop de tentatives (message traduit par Symfony)
  const erreur =
    reponse.status === 401
      ? (reponse.corps?.error ?? 'Adresse e-mail ou mot de passe incorrect.')
      : 'La connexion a échoué. Réessayez dans un instant.'

  return { erreur, email }
}

export async function deconnexionAction({ request }) {
  await envoyerJson('/api/logout', { signal: request.signal })

  return redirect('/')
}
