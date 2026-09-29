import { Form, useActionData, useNavigation } from 'react-router'

/**
 * Formulaire de connexion. L'envoi est traité par connexionAction (POST /api/login),
 * puis l'utilisateur revient à la page qu'il consultait.
 */
export default function Connexion() {
  const resultat = useActionData()
  const navigation = useNavigation()
  const envoiEnCours = navigation.state === 'submitting'

  return (
    <main className="connexion">
      <title>Connexion — Instruments traditionnels</title>
      <h1 className="connexion__titre">Connexion à votre compte</h1>

      <Form method="post" className="formulaire">
        {resultat?.erreur && (
          <p className="formulaire__erreur" role="alert">
            {resultat.erreur}
          </p>
        )}

        <label className="champ">
          <span className="champ__libelle">Adresse e-mail</span>
          <input
            type="email"
            name="email"
            autoComplete="username"
            required
            defaultValue={resultat?.email ?? ''}
            aria-invalid={resultat?.erreur ? true : undefined}
          />
        </label>

        <label className="champ">
          <span className="champ__libelle">Mot de passe</span>
          <input
            type="password"
            name="password"
            autoComplete="current-password"
            required
            aria-invalid={resultat?.erreur ? true : undefined}
          />
        </label>

        <button type="submit" className="bouton" disabled={envoiEnCours}>
          {envoiEnCours ? 'Connexion en cours…' : 'Se connecter'}
        </button>
      </Form>

      {import.meta.env.DEV && (
        <aside className="connexion__dev">
          <h2>Comptes de développement</h2>
          <p>
            <code>admin@instruments.test</code> / <code>admin</code>
            <br />
            <code>marie.dubois@example.test</code> / <code>client</code>
          </p>
        </aside>
      )}
    </main>
  )
}
