import { data, redirect } from 'react-router'
import { creerCommande, fetchCommande, fetchCommandes, fetchOptionsLivraison } from './api/commandes.js'
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
  const jeton = jetonValide(new URL(request.url).searchParams.get('jeton'))
  const reponse = await fetchCommande(params.numero, jeton, request.signal)

  if (reponse.ok) return { commande: reponse.corps, jeton }
  if ([401, 403, 404].includes(reponse.status)) return { commande: null, jeton }

  throw data(null, { status: reponse.status })
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
