import { useState } from 'react'
import { Form, Link, useActionData, useLoaderData, useNavigate, useNavigation, useRouteLoaderData } from 'react-router'
import { formaterDelai } from '../lib/commande.js'
import { formaterPrix, nomPays } from '../lib/format.js'

/**
 * Page /commande : coordonnées, livraison, facturation et récapitulatif sur une seule page.
 * Les articles viennent du panier de la route racine ; le serveur recalcule tout et refuse (409)
 * si le total affiché ici ne correspond plus au sien.
 */

const MESSAGES_LIVRAISON = {
  pays_non_livrable: "Nous ne livrons pas encore dans ce pays : seul le retrait à l'atelier est possible.",
  retrait_uniquement: "Un instrument de votre panier ne peut être que retiré à l'atelier.",
  transport_sur_devis:
    'Un instrument de votre panier demande un transport spécialisé, sur devis : contactez-nous, ou choisissez le retrait.',
}

/**
 * Clé d'idempotence (8 à 64 caractères [A-Za-z0-9_-]) : un double clic renvoie la même commande.
 */
function nouvelleCle() {
  if (globalThis.crypto?.randomUUID) return crypto.randomUUID()

  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`
}

function Champ({ nom, libelle, violations, type = 'text', requis = true, valeurParDefaut, autoComplete, ...autres }) {
  const erreur = violations[nom]
  const idErreur = `${nom}-erreur`

  return (
    <label className="champ">
      <span className="champ__libelle">
        {libelle}
        {!requis && <span className="champ__facultatif"> (facultatif)</span>}
      </span>
      <input
        type={type}
        name={nom}
        required={requis}
        defaultValue={valeurParDefaut}
        autoComplete={autoComplete}
        aria-invalid={erreur ? 'true' : undefined}
        aria-describedby={erreur ? idErreur : undefined}
        {...autres}
      />
      {erreur && (
        <span id={idErreur} className="champ__erreur">
          {erreur}
        </span>
      )}
    </label>
  )
}

/**
 * Champs d'une adresse ; le pays de livraison est imposé par le sélecteur de la section « Livraison ».
 */
function ChampsAdresse({ prefixe, violations, utilisateur, pays, paysPossibles, section }) {
  const auto = (valeur) => `section-${section} ${valeur}`

  return (
    <div className="commande__grille">
      <Champ nom={`${prefixe}.prenom`} libelle="Prénom" violations={violations} valeurParDefaut={utilisateur?.prenom} autoComplete={auto('given-name')} maxLength={100} />
      <Champ nom={`${prefixe}.nom`} libelle="Nom" violations={violations} valeurParDefaut={utilisateur?.nom} autoComplete={auto('family-name')} maxLength={100} />
      <Champ nom={`${prefixe}.societe`} libelle="Société" requis={false} violations={violations} autoComplete={auto('organization')} maxLength={150} />
      <Champ nom={`${prefixe}.rue`} libelle="Rue et numéro" violations={violations} autoComplete={auto('address-line1')} maxLength={255} />
      <Champ nom={`${prefixe}.complement`} libelle="Complément d'adresse" requis={false} violations={violations} autoComplete={auto('address-line2')} maxLength={255} />
      <Champ nom={`${prefixe}.codePostal`} libelle="Code postal" violations={violations} autoComplete={auto('postal-code')} maxLength={20} pattern="[A-Za-z0-9 \-]+" />
      <Champ nom={`${prefixe}.ville`} libelle="Ville" violations={violations} autoComplete={auto('address-level2')} maxLength={100} />
      {pays ? (
        <p className="champ">
          <span className="champ__libelle">Pays</span>
          <span>{nomPays(pays)}</span>
          <input type="hidden" name={`${prefixe}.pays`} value={pays} />
        </p>
      ) : (
        <label className="champ">
          <span className="champ__libelle">Pays</span>
          <select name={`${prefixe}.pays`} required defaultValue="BE" autoComplete={auto('country')}>
            {paysPossibles.map((code) => (
              <option key={code} value={code}>
                {nomPays(code)}
              </option>
            ))}
          </select>
          {violations[`${prefixe}.pays`] && <span className="champ__erreur">{violations[`${prefixe}.pays`]}</span>}
        </label>
      )}
    </div>
  )
}

export default function Commande() {
  const { utilisateur, panier } = useRouteLoaderData('racine') ?? {}
  const { livraison } = useLoaderData()
  const actionData = useActionData()
  const navigation = useNavigation()
  const navigate = useNavigate()
  const envoiEnCours = navigation.state === 'submitting'
  // Pendant le rechargement (après un refus, un changement de pays…) le total affiché peut être périmé
  const occupe = navigation.state !== 'idle'

  const [modeChoisi, setModeChoisi] = useState(null)
  const [memeFacturation, setMemeFacturation] = useState(true)
  const [cle, setCle] = useState(nouvelleCle)
  const [reponseVue, setReponseVue] = useState(actionData)

  // Le serveur a répondu (refus) : la prochaine tentative est une nouvelle demande, donc une nouvelle clé
  if (actionData !== reponseVue) {
    setReponseVue(actionData)
    if (actionData?.serveur) setCle(nouvelleCle())
  }

  const titre = <title>Commande — Instruments traditionnels</title>

  if (!panier || !livraison) {
    return (
      <main className="commande">
        {titre}
        <h1 className="commande__titre">Commande</h1>
        <p className="formulaire__erreur" role="alert">
          La commande est momentanément indisponible. Rechargez la page dans un instant.
        </p>
      </main>
    )
  }

  if (panier.lignes.length === 0 || !panier.commandable) {
    return (
      <main className="commande">
        {titre}
        <h1 className="commande__titre">Commande</h1>
        <p className="commande__intro">
          {panier.lignes.length === 0
            ? 'Votre panier est vide.'
            : 'Certains instruments de votre panier ne sont plus disponibles.'}
        </p>
        <Link to={panier.lignes.length === 0 ? '/' : '/panier'} className="lien-retour">
          {panier.lignes.length === 0 ? 'Découvrir le catalogue' : 'Revoir mon panier'}
        </Link>
      </main>
    )
  }

  const violations = actionData?.violations ?? {}
  const options = livraison.options ?? []
  const option = options.find((o) => o.code === modeChoisi) ?? options[0] ?? null
  const retrait = option?.retrait ?? false
  const total = panier.totalTtc + (option?.prixTtc ?? 0)
  const lignes = panier.lignes.filter((ligne) => ligne.disponible)

  function changerPays(evenement) {
    navigate(`/commande?pays=${encodeURIComponent(evenement.target.value)}`, { replace: true, preventScrollReset: true })
  }

  return (
    <main className="commande">
      {titre}
      <h1 className="commande__titre">Commande</h1>

      <Form method="post" className="commande__corps">
        <input type="hidden" name="cle" value={cle} />
        <input type="hidden" name="totalTtcAttendu" value={total} />
        <input type="hidden" name="retrait" value={retrait ? '1' : '0'} />

        <div className="commande__etapes">
          {actionData?.erreur && (
            <p className="formulaire__erreur" role="alert">
              {actionData.erreur}
            </p>
          )}

          <fieldset className="commande__section">
            <legend>1. Vos coordonnées</legend>
            {utilisateur ? (
              <p className="commande__note">
                Commande passée avec le compte <strong>{utilisateur.email}</strong>.
              </p>
            ) : (
              <p className="commande__note">
                Pas besoin de compte.{' '}
                <Link to={`/connexion?retour=${encodeURIComponent('/commande')}`}>Déjà client ? Connectez-vous</Link>.
              </p>
            )}
            <div className="commande__grille">
              {!utilisateur && (
                <Champ nom="email" type="email" libelle="Adresse e-mail" violations={violations} autoComplete="email" maxLength={180} />
              )}
              <Champ
                nom="telephone"
                type="tel"
                libelle="Téléphone"
                requis={false}
                violations={violations}
                autoComplete="tel"
                maxLength={30}
                pattern="[0-9 +().\-]*"
              />
            </div>
          </fieldset>

          <fieldset className="commande__section">
            <legend>2. Livraison</legend>
            <label className="champ">
              <span className="champ__libelle">Pays de livraison</span>
              <select value={livraison.pays ?? ''} onChange={changerPays} disabled={occupe}>
                {!livraison.pays && <option value="">Choisissez un pays</option>}
                {livraison.paysLivrables.map((code) => (
                  <option key={code} value={code}>
                    {nomPays(code)}
                  </option>
                ))}
              </select>
            </label>

            {(livraison.messages ?? []).map((message) => (
              <p key={message} className="commande__alerte" role="status">
                {MESSAGES_LIVRAISON[message] ?? message}
              </p>
            ))}

            {options.length === 0 ? (
              <p className="formulaire__erreur" role="alert">
                Aucun mode de livraison n'est disponible pour ce panier et ce pays.
              </p>
            ) : (
              <div className="options-livraison" role="radiogroup" aria-label="Mode de livraison">
                {options.map((o) => (
                  <label key={o.code} className="option-livraison">
                    <input
                      type="radio"
                      name="modeLivraison"
                      value={o.code}
                      checked={option?.code === o.code}
                      onChange={() => setModeChoisi(o.code)}
                    />
                    <span className="option-livraison__texte">
                      <span className="option-livraison__nom">{o.nom}</span>
                      {o.description && <span className="option-livraison__description">{o.description}</span>}
                      {formaterDelai(o) && <span className="option-livraison__description">{formaterDelai(o)}</span>}
                    </span>
                    <span className="option-livraison__prix">{o.prixTtc > 0 ? formaterPrix(o.prixTtc) : 'Gratuit'}</span>
                  </label>
                ))}
              </div>
            )}
            {violations.modeLivraison && <p className="champ__erreur">{violations.modeLivraison}</p>}

            {!retrait && option && (
              <>
                <h2 className="commande__sous-titre">Adresse de livraison</h2>
                <ChampsAdresse
                  prefixe="adresseLivraison"
                  section="livraison"
                  violations={violations}
                  utilisateur={utilisateur}
                  pays={livraison.pays}
                />
              </>
            )}
          </fieldset>

          <fieldset className="commande__section">
            <legend>3. Facturation</legend>
            {!retrait && (
              <label className="case">
                <input
                  type="checkbox"
                  name="memeFacturation"
                  checked={memeFacturation}
                  onChange={(e) => setMemeFacturation(e.target.checked)}
                />
                Adresse de facturation identique à l'adresse de livraison
              </label>
            )}
            {(retrait || !memeFacturation) && (
              <ChampsAdresse
                prefixe="adresseFacturation"
                section="facturation"
                violations={violations}
                utilisateur={utilisateur}
                paysPossibles={livraison.paysLivrables}
              />
            )}

            <label className="champ">
              <span className="champ__libelle">
                Message pour l'atelier<span className="champ__facultatif"> (facultatif)</span>
              </span>
              <textarea name="commentaire" rows={3} maxLength={2000} />
            </label>
          </fieldset>
        </div>

        <aside className="recap" aria-labelledby="titre-recap-commande">
          <h2 id="titre-recap-commande" className="recap__titre">
            Récapitulatif
          </h2>
          <ul className="detail-commande__lignes">
            {lignes.map((ligne) => (
              <li key={ligne.id}>
                <span className="detail-commande__libelle">
                  {ligne.instrument.nom}
                  <span className="detail-commande__quantite"> × {ligne.quantite}</span>
                </span>
                <span className="detail-commande__montant">{formaterPrix(ligne.totalTtc)}</span>
              </li>
            ))}
          </ul>
          <dl className="recap__liste">
            <div>
              <dt>Sous-total</dt>
              <dd>{formaterPrix(panier.sousTotalTtc)}</dd>
            </div>
            <div>
              <dt>Frais de port</dt>
              <dd>{option ? (option.prixTtc > 0 ? formaterPrix(option.prixTtc) : 'Gratuit') : '—'}</dd>
            </div>
            <div className="recap__total">
              <dt>Total TTC</dt>
              <dd>{formaterPrix(total)}</dd>
            </div>
          </dl>

          <label className="case">
            <input type="checkbox" name="cgv" required />
            <span>
              J'accepte les{' '}
              <Link to="/cgv" target="_blank" rel="noopener">
                conditions générales de vente
              </Link>
              .
            </span>
          </label>
          {violations.accepteCgv && <p className="champ__erreur">{violations.accepteCgv}</p>}

          <button type="submit" className="bouton recap__commander" disabled={occupe || !option}>
            {envoiEnCours ? 'Enregistrement…' : 'Valider la commande'}
          </button>
          <p className="recap__note">
            Vos instruments sont réservés pendant 30 minutes après la validation ; vous choisissez ensuite votre moyen de
            paiement.
          </p>
          <Link to="/panier" className="lien-retour">
            Modifier le panier
          </Link>
        </aside>
      </Form>
    </main>
  )
}
