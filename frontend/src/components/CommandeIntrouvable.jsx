import { Link } from 'react-router'

/**
 * Message affiché quand la commande n'existe pas, n'est pas à vous ou que le lien est incomplet.
 */
export default function CommandeIntrouvable() {
  return (
    <main className="commande">
      <title>Commande introuvable — Instruments traditionnels</title>
      <h1 className="commande__titre">Commande introuvable ou lien incomplet</h1>
      <p className="commande__intro">
        Vérifiez que vous avez copié le lien complet reçu par e-mail, ou connectez-vous au compte qui a passé la
        commande.
      </p>
      <p className="erreur__actions">
        <Link to="/connexion?retour=/compte/commandes" className="bouton">
          Me connecter
        </Link>
        <Link to="/" className="lien-retour">
          Voir tout le catalogue
        </Link>
      </p>
    </main>
  )
}
