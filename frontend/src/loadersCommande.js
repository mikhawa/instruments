import { data, redirect } from 'react-router'
import {
  choisirIssueSimulation,
  creerCommande,
  demanderPaiement,
  fetchCommande,
  fetchCommandes,
  fetchOptionsLivraison,
  fetchSimulation,
  verifierPaiement,
} from './api/commandes.js'
import { jetonValide } from './lib/commande.js'

/**
 * Loaders et action du tunnel de commande et de l'espace « Mes commandes ».
 */

const PAYS_PAR_DEFAUT = 'BE'

/**
 * /commande : options de livraison pour le pays de l'URL (?pays=FR), Belgique par défaut.
 * Le formulaire recharge ce loader avec un fetcher quand le pays change.
 */
export async function commandeLoader({ request }) {
  const pays = new URL(request.url).searchParams.get('pays') ?? PAYS_PAR_DEFAUT
  const reponse = await fetchOptionsLivraison(/^[A-Za-z]{2}$/.test(pays) ? pays.toUpperCase() : PAYS_PAR_DEFAUT, request.signal)

  return { livraison: reponse.ok ? reponse.corps : null }
}

/**
 * Texte d'erreur d'une commande refusée, par code HTTP.
 */
function messageRefus(reponse) {
  const corps = reponse.corps
  switch (reponse.status) {
    case 409:
      return (
        corps?.detail ??
        'Votre panier a changé (prix, stock ou livraison). Vérifiez le récapitulatif puis validez à nouveau.'
      )
    case 422:
      return corps?.detail ?? 'Certaines informations sont à corriger.'
    case 429:
      return 'Trop de commandes en peu de temps. Patientez quelques minutes avant de réessayer.'
    case 400:
      return "La demande n'a pas pu être traitée. Rechargez la page puis réessayez."
    default:
      return 'La commande est momentanément indisponible. Réessayez dans un instant.'
  }
}

function texte(formulaire, nom) {
  return String(formulaire.get(nom) ?? '').trim()
}

function ouNull(valeur) {
  return valeur === '' ? null : valeur
}

function adresse(formulaire, prefixe) {
  return {
    prenom: texte(formulaire, `${prefixe}.prenom`),
    nom: texte(formulaire, `${prefixe}.nom`),
    societe: ouNull(texte(formulaire, `${prefixe}.societe`)),
    rue: texte(formulaire, `${prefixe}.rue`),
    complement: ouNull(texte(formulaire, `${prefixe}.complement`)),
    codePostal: texte(formulaire, `${prefixe}.codePostal`),
    ville: texte(formulaire, `${prefixe}.ville`),
    pays: texte(formulaire, `${prefixe}.pays`),
  }
}

/**
 * Envoi de la commande (POST /api/commandes). Succès : redirection vers la confirmation.
 * Échec : { erreur, violations: { 'adresseLivraison.ville': message }, serveur }.
 * `serveur` indique que le serveur a répondu (une nouvelle clé d'idempotence est alors utilisée) ;
 * React Router revalide ensuite les loaders, donc le panier affiché se met à jour (409).
 */
export async function commandeAction({ request }) {
  const formulaire = await request.formData()
  const retrait = texte(formulaire, 'retrait') === '1'
  const memeFacturation = formulaire.get('memeFacturation') === 'on'
  const email = texte(formulaire, 'email')

  const corps = {
    telephone: ouNull(texte(formulaire, 'telephone')),
    adresseLivraison: retrait ? null : adresse(formulaire, 'adresseLivraison'),
    adresseFacturation: memeFacturation && !retrait ? null : adresse(formulaire, 'adresseFacturation'),
    modeLivraison: texte(formulaire, 'modeLivraison'),
    commentaire: ouNull(texte(formulaire, 'commentaire')),
    accepteCgv: formulaire.get('cgv') === 'on',
    totalTtcAttendu: Number.parseInt(texte(formulaire, 'totalTtcAttendu'), 10),
  }
  if (email) corps.email = email

  let reponse
  try {
    reponse = await creerCommande(corps, texte(formulaire, 'cle'), request.signal)
  } catch {
    // Réseau coupé : on garde la même clé, un nouvel essai ne peut pas créer de doublon
    return { erreur: 'Le serveur est injoignable. Vérifiez votre connexion puis réessayez.', violations: {}, serveur: false }
  }

  if (reponse.ok && reponse.corps?.numero) {
    const jeton = reponse.corps.jetonSuivi
    const requete = jeton ? `?jeton=${encodeURIComponent(jeton)}` : ''

    // Un seul moyen proposé (en ligne) : on évite une étape au client en demandant le paiement tout de suite.
    // En cas d'échec, la page de confirmation propose le choix.
    const moyens = reponse.corps.moyensPaiement ?? []
    if (moyens.length === 1 && moyens[0] === 'en_ligne') {
      try {
        const paiement = await demanderPaiement(reponse.corps.numero, jeton ?? null, 'en_ligne', request.signal)
        if (paiement.ok && paiement.corps?.urlPaiement) return redirect(paiement.corps.urlPaiement)
      } catch {
        // Prestataire injoignable : on retombe sur la page de confirmation
      }
    }

    return redirect(`/commande/confirmation/${encodeURIComponent(reponse.corps.numero)}${requete}`)
  }

  const violations = {}
  for (const violation of reponse.corps?.violations ?? []) {
    if (violation.propertyPath && !violations[violation.propertyPath]) {
      violations[violation.propertyPath] = violation.message
    }
  }

  return { erreur: messageRefus(reponse), violations, status: reponse.status, serveur: true }
}

/**
 * Détail d'une commande (confirmation et suivi) : le jeton vient de l'URL.
 * Introuvable ou non autorisé : { commande: null } pour afficher un message clair dans la page.
 */
export async function detailCommandeLoader({ params, request }) {
  const url = new URL(request.url)
  const jeton = jetonValide(url.searchParams.get('jeton'))
  const retour = url.searchParams.get('retour') === '1'

  // Retour de la page du prestataire : le serveur relit le statut du paiement chez lui.
  // Si la vérification est refusée (trop d'appels, panne), on affiche au moins la commande telle quelle.
  let reponse = retour ? await verifierPaiement(params.numero, jeton, request.signal).catch(() => null) : null
  if (!reponse?.ok) reponse = await fetchCommande(params.numero, jeton, request.signal)

  if (reponse.ok) return { commande: reponse.corps, jeton }
  if ([401, 403, 404].includes(reponse.status)) return { commande: null, jeton }

  throw data(null, { status: reponse.status })
}

/**
 * Action de la confirmation et du suivi : choix du moyen de paiement.
 * En ligne : redirection vers la page du prestataire. Virement : la page est rechargée et affiche le bloc virement.
 * Échec : { erreur } affiché dans la page.
 */
export async function paiementAction({ params, request }) {
  const formulaire = await request.formData()
  const moyen = String(formulaire.get('moyen') ?? '')
  const jeton = jetonValide(new URL(request.url).searchParams.get('jeton'))

  if (!['en_ligne', 'virement'].includes(moyen)) return { erreur: "Ce moyen de paiement n'est pas valide." }

  let reponse
  try {
    reponse = await demanderPaiement(params.numero, jeton, moyen, request.signal)
  } catch {
    return { erreur: 'Le serveur est injoignable. Vérifiez votre connexion puis réessayez.' }
  }

  if (reponse.ok) {
    if (moyen === 'en_ligne' && reponse.corps?.urlPaiement) return redirect(reponse.corps.urlPaiement)

    return { erreur: null }
  }

  switch (reponse.status) {
    case 404:
      return { erreur: "Cette commande n'est pas accessible. Vérifiez le lien reçu par e-mail." }
    case 409:
      return { erreur: reponse.corps?.detail ?? "Cette commande ne peut plus être payée par ce moyen." }
    case 422:
      return { erreur: reponse.corps?.detail ?? "Ce moyen de paiement n'est pas valide." }
    case 429:
      return { erreur: 'Trop de tentatives en peu de temps. Patientez quelques minutes avant de réessayer.' }
    case 503:
      return { erreur: reponse.corps?.detail ?? 'Le service de paiement ne répond pas. Réessayez dans un instant.' }
    default:
      return { erreur: 'Le paiement est momentanément indisponible. Réessayez dans un instant.' }
  }
}

/**
 * /paiement/simulation/:id (dev et test) : l'API répond 404 en production → page d'erreur de route.
 */
export async function simulationLoader({ params, request }) {
  const reponse = await fetchSimulation(params.id, request.signal)
  if (!reponse.ok) throw data(null, { status: reponse.status })

  return { simulation: reponse.corps }
}

/**
 * Issue choisie sur la page de simulation : le serveur termine le paiement puis renvoie l'URL de retour.
 */
export async function simulationAction({ params, request }) {
  const formulaire = await request.formData()
  const reponse = await choisirIssueSimulation(params.id, String(formulaire.get('issue') ?? ''), request.signal)

  if (reponse.ok && reponse.corps?.urlRetour) return redirect(reponse.corps.urlRetour)

  return { erreur: reponse.corps?.detail ?? 'La simulation a échoué. Rechargez la page puis réessayez.' }
}

/**
 * /compte/commandes : liste du client connecté, sinon connexion puis retour ici.
 */
export async function mesCommandesLoader({ request }) {
  const reponse = await fetchCommandes(request.signal)
  if (reponse.status === 401) {
    throw redirect(`/connexion?retour=${encodeURIComponent('/compte/commandes')}`)
  }
  if (!reponse.ok) throw data(null, { status: reponse.status })

  const corps = reponse.corps
  const commandes = Array.isArray(corps) ? corps : (corps?.member ?? corps?.['hydra:member'] ?? [])

  return { commandes }
}
