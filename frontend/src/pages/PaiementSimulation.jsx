import { Form, useActionData, useLoaderData, useNavigation } from 'react-router'
import { formaterPrix } from '../lib/format.js'

/**
 * Libellés des issues proposées par la simulation.
 */
const ISSUES = {
  paye: 'Paiement accepté',
  en_attente: 'Paiement en attente',
  echoue: 'Paiement refusé',
  annule: 'Paiement annulé',
  expire: 'Paiement expiré',
}

/**
 * Page /paiement/simulation/:id : remplace la page du prestataire en développement.
 * Chaque bouton termine le paiement avec l'issue choisie puis renvoie vers la confirmation.
 */
export default function PaiementSimulation() {
  const { simulation } = useLoaderData()
  const actionData = useActionData()
  const navigation = useNavigation()
  const occupe = navigation.state !== 'idle'
  const termine = simulation.statut && !['ouvert', 'en_attente'].includes(simulation.statut)

  return (
    <main className="commande">
      <title>Simulation de paiement — Instruments traditionnels</title>
      <p className="simulation__bandeau" role="note">
        Simulation de paiement — environnement de développement, aucun argent n'est débité
      </p>
      <h1 className="commande__titre">Paiement de la commande {simulation.commande}</h1>

      <dl className="simulation__resume">
        <div>
          <dt>Commande</dt>
          <dd>{simulation.commande}</dd>
        </div>
        <div>
          <dt>Montant</dt>
          <dd>{formaterPrix(simulation.montant)}</dd>
        </div>
      </dl>

      {termine && (
        <p className="commande__alerte" role="status">
          Ce paiement simulé est déjà terminé ({simulation.statut}).
        </p>
      )}
      {actionData?.erreur && (
        <p className="formulaire__erreur" role="alert">
          {actionData.erreur}
        </p>
      )}

      <Form method="post" className="simulation__issues">
        <p className="commande__note">Choisissez le résultat du paiement :</p>
        <div className="simulation__boutons">
          {(simulation.issues ?? Object.keys(ISSUES)).map((issue) => (
            <button
              key={issue}
              type="submit"
              name="issue"
              value={issue}
              className={`bouton${issue === 'paye' ? '' : ' bouton--discret'}`}
              disabled={occupe}
            >
              {ISSUES[issue] ?? issue}
            </button>
          ))}
        </div>
      </Form>
    </main>
  )
}
