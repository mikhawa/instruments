import { useEffect, useRef, useState } from 'react'
import { Form, Link, useActionData, useNavigation, useRevalidator, useSearchParams } from 'react-router'
import BlocVirement from './BlocVirement.jsx'
import { formaterDateHeure, paiementEnCours, paiementNonAbouti } from '../lib/commande.js'

/**
 * Durée de l'attente de la confirmation de la banque après le retour du prestataire.
 */
const INTERVALLE_MS = 3000
const DUREE_MAX_MS = 60000

const STATUTS_PAYES = ['payee', 'en_preparation', 'expediee', 'livree']

/**
 * Boutons de choix du moyen de paiement (soumission POST vers l'action de la route).
 * `moyens` : liste proposée par l'API (« en_ligne », « virement »).
 */
function Choix({ moyens, virementEnCours }) {
  const actionData = useActionData()
  const navigation = useNavigation()
  const occupe = navigation.state !== 'idle'

  const proposeVirement = moyens.includes('virement') && !virementEnCours

  return (
    <Form method="post" className="choix-paiement">
      {actionData?.erreur && (
        <p className="formulaire__erreur" role="alert">
          {actionData.erreur}
        </p>
      )}
      <div className="choix-paiement__boutons">
        {moyens.includes('en_ligne') && (
          <button type="submit" name="moyen" value="en_ligne" className="choix-paiement__option" disabled={occupe}>
            <span className="choix-paiement__nom">Payer en ligne</span>
            <span className="choix-paiement__detail">Bancontact, carte, PayPal, Apple Pay…</span>
          </button>
        )}
        {proposeVirement && (
          <button type="submit" name="moyen" value="virement" className="choix-paiement__option" disabled={occupe}>
            <span className="choix-paiement__nom">Payer par virement</span>
            <span className="choix-paiement__detail">Sans frais, réservation prolongée de 5 jours ouvrables</span>
          </button>
        )}
      </div>
    </Form>
  )
}

/**
 * Attente de la confirmation de la banque : revalide le loader (qui relit le statut chez le prestataire)
 * toutes les 3 s, pendant 60 s au plus.
 */
function AttenteBanque() {
  const revalidator = useRevalidator()
  const [delaiDepasse, setDelaiDepasse] = useState(false)
  const revalidatorRef = useRef(revalidator)

  useEffect(() => {
    revalidatorRef.current = revalidator
  })

  useEffect(() => {
    const debut = Date.now()
    const minuteur = setInterval(() => {
      if (Date.now() - debut >= DUREE_MAX_MS) {
        clearInterval(minuteur)
        setDelaiDepasse(true)

        return
      }
      if (revalidatorRef.current.state === 'idle') revalidatorRef.current.revalidate()
    }, INTERVALLE_MS)

    return () => clearInterval(minuteur)
  }, [])

  if (delaiDepasse) {
    return (
      <p className="commande__alerte" role="status">
        <strong>Votre banque n'a pas encore confirmé le paiement.</strong> Rechargez cette page dans quelques minutes ;
        un e-mail de confirmation vous sera envoyé dès que le paiement sera reçu.
      </p>
    )
  }

  return (
    <p className="commande__alerte attente-paiement" role="status" aria-live="polite">
      <span className="attente-paiement__rond" aria-hidden="true" />
      Nous attendons la confirmation de votre banque…
    </p>
  )
}

/**
 * Section « paiement » de la confirmation et du suivi : choix du moyen, virement, attente de la banque,
 * succès, échec, expiration. Elle ne dit « payée » que si le statut de la commande l'est.
 *
 * @param {{ commande: object }} props
 */
export default function ChoixPaiement({ commande }) {
  const [parametres] = useSearchParams()
  const retour = parametres.get('retour') === '1'

  const { statut, paiement } = commande
  const moyens = commande.moyensPaiement ?? []
  const enCours = paiementEnCours(paiement)
  const virementEnCours = enCours && paiement.type === 'virement' && Boolean(paiement.virement)
  const enLigneEnCours = enCours && paiement.type === 'en_ligne'

  if (STATUTS_PAYES.includes(statut)) {
    return (
      <p className="commande__succes" role="status">
        <strong>Paiement reçu, merci !</strong> Nous préparons votre commande ; vous serez prévenu par e-mail à chaque
        étape.
      </p>
    )
  }

  if (statut === 'remboursee') {
    return (
      <p className="commande__alerte" role="status">
        <strong>Votre paiement vous a été remboursé.</strong> La commande avait été annulée avant l'arrivée du
        paiement : le montant vous est rendu intégralement.
      </p>
    )
  }

  if (statut === 'annulee') {
    return (
      <div className="commande__alerte" role="status">
        <p>
          <strong>Cette commande est annulée.</strong> La réservation des instruments a expiré avant le paiement.
        </p>
        <p>
          <Link to="/panier">Retourner au panier</Link> pour recommencer.
        </p>
      </div>
    )
  }

  if (statut !== 'en_attente_paiement') return null

  const echec = !enCours && paiementNonAbouti(paiement)

  return (
    <section className="paiement" aria-labelledby="titre-paiement">
      <h2 id="titre-paiement">Paiement</h2>

      {enLigneEnCours && retour && <AttenteBanque />}

      {enLigneEnCours && !retour && (
        <p className="commande__alerte" role="status">
          <strong>Un paiement en ligne est en cours.</strong> Si vous ne l'avez pas terminé, vous pouvez le reprendre.
          {paiement.urlPaiement && (
            <>
              {' '}
              <a href={paiement.urlPaiement}>Reprendre le paiement</a>.
            </>
          )}
        </p>
      )}

      {echec && (
        <p className="commande__alerte" role="status">
          <strong>Le paiement n'a pas abouti.</strong> Aucun montant n'a été débité
          {moyens.length > 0 ? ' : vous pouvez réessayer.' : '.'}
        </p>
      )}

      {moyens.length === 0 ? (
        !enCours && (
          <div className="commande__alerte" role="status">
            <p>
              <strong>La réservation de vos instruments a expiré.</strong> Cette commande ne peut plus être payée.
            </p>
            <p>
              <Link to="/panier">Retourner au panier</Link> pour recommencer.
            </p>
          </div>
        )
      ) : (
        <>
          {!enCours && !echec && (
            <p className="commande__alerte" role="status">
              <strong>Elle n'est pas encore payée.</strong>
              {commande.expireAt && <> Vos instruments sont réservés jusqu'à {formaterDateHeure(commande.expireAt)}.</>}
            </p>
          )}
          {virementEnCours && <BlocVirement virement={paiement.virement} expireAt={commande.expireAt} />}
          <h3 className="paiement__sous-titre">
            {virementEnCours ? 'Vous préférez payer en ligne ?' : 'Choisissez votre moyen de paiement'}
          </h3>
          <Choix moyens={virementEnCours ? moyens.filter((m) => m !== 'virement') : moyens} virementEnCours={virementEnCours} />
        </>
      )}
    </section>
  )
}
