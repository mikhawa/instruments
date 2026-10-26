import { formaterPrix, nomPays } from '../lib/format.js'
import { formaterDate, formaterDateHeure, libelleStatut } from '../lib/commande.js'

const ETAPES = [
  { statut: 'en_attente_paiement', libelle: 'Commandée' },
  { statut: 'payee', libelle: 'Payée' },
  { statut: 'en_preparation', libelle: 'En préparation' },
  { statut: 'expediee', libelle: 'Expédiée' },
  { statut: 'livree', libelle: 'Livrée' },
]

const STATUTS_A_PART = ['annulee', 'retournee', 'remboursee']

/**
 * Frise des étapes du statut. Annulée, retournée et remboursée sortent du parcours normal :
 * elles sont affichées à part, sans frise.
 */
export function FriseStatut({ commande }) {
  if (STATUTS_A_PART.includes(commande.statut)) {
    return (
      <p className="frise__a-part" role="status">
        <strong>{libelleStatut(commande.statut)}</strong>
        {commande.statut === 'annulee' && commande.cancelledAt && <> le {formaterDateHeure(commande.cancelledAt)}</>}
      </p>
    )
  }

  const courante = ETAPES.findIndex((etape) => etape.statut === commande.statut)

  return (
    <ol className="frise" aria-label="Avancement de la commande">
      {ETAPES.map((etape, index) => (
        <li
          key={etape.statut}
          className={`frise__etape${index < courante ? ' frise__etape--faite' : ''}${index === courante ? ' frise__etape--courante' : ''}`}
          aria-current={index === courante ? 'step' : undefined}
        >
          <span className="frise__libelle">{etape.libelle}</span>
          {index === courante && commande.statut === 'en_attente_paiement' && (
            <span className="frise__note">en attente de paiement</span>
          )}
        </li>
      ))}
    </ol>
  )
}

function BlocAdresse({ titre, adresse }) {
  if (!adresse) return null

  return (
    <section className="detail-commande__bloc">
      <h3>{titre}</h3>
      <address className="adresse">
        {adresse.prenom} {adresse.nom}
        {adresse.societe && (
          <>
            <br />
            {adresse.societe}
          </>
        )}
        <br />
        {adresse.rue}
        {adresse.complement && (
          <>
            <br />
            {adresse.complement}
          </>
        )}
        <br />
        {adresse.codePostal} {adresse.ville}
        <br />
        {nomPays(adresse.pays)}
      </address>
    </section>
  )
}

/**
 * Factures et notes de crédit à télécharger (PDF servi par l'API, avec le jeton du lien de suivi pour un invité).
 */
function ListeFactures({ commande, jeton }) {
  const factures = commande.factures ?? []
  if (factures.length === 0) return null
  const requete = jeton ? `?jeton=${encodeURIComponent(jeton)}` : ''

  return (
    <section className="detail-commande__bloc">
      <h3>Factures</h3>
      <ul className="detail-commande__factures">
        {factures.map((facture) => (
          <li key={facture.numero}>
            <a
              href={`/api/commandes/${encodeURIComponent(commande.numero)}/factures/${encodeURIComponent(facture.numero)}.pdf${requete}`}
              target="_blank"
              rel="noopener"
            >
              {facture.type === 'note_credit' ? 'Note de crédit' : 'Facture'} {facture.numero}
            </a>
            <span className="detail-commande__montant">
              {formaterDate(facture.dateEmission)} · {formaterPrix(facture.totalTtc)}
            </span>
          </li>
        ))}
      </ul>
    </section>
  )
}

/**
 * Détail d'une commande, commun à la confirmation et au suivi : articles, totaux, factures, adresses, livraison.
 *
 * @param {{ commande: object, frise?: boolean, jeton?: string | null }} props
 */
export default function DetailCommande({ commande, frise = false, jeton = null }) {
  return (
    <div className="detail-commande">
      <dl className="detail-commande__entete">
        <div>
          <dt>Commande</dt>
          <dd>{commande.numero}</dd>
        </div>
        <div>
          <dt>Passée le</dt>
          <dd>{formaterDateHeure(commande.createdAt)}</dd>
        </div>
        <div>
          <dt>Statut</dt>
          <dd>
            <span className={`statut statut--${commande.statut}`}>{libelleStatut(commande.statut)}</span>
          </dd>
        </div>
      </dl>

      {frise && <FriseStatut commande={commande} />}

      {commande.numeroSuivi && (
        <p className="detail-commande__suivi">
          Numéro de suivi du transporteur : <strong>{commande.numeroSuivi}</strong>
          {commande.shippedAt && <> (expédiée le {formaterDateHeure(commande.shippedAt)})</>}
        </p>
      )}
      {commande.deliveredAt && <p className="detail-commande__suivi">Livrée le {formaterDateHeure(commande.deliveredAt)}.</p>}

      <section className="detail-commande__bloc">
        <h3>Articles</h3>
        <ul className="detail-commande__lignes">
          {commande.lignes.map((ligne) => (
            <li key={ligne.reference}>
              <span className="detail-commande__libelle">
                {ligne.libelle}
                <span className="detail-commande__quantite">
                  {' '}
                  × {ligne.quantite} ({formaterPrix(ligne.prixUnitaireTtc)} l'unité)
                </span>
              </span>
              <span className="detail-commande__montant">{formaterPrix(ligne.totalTtc)}</span>
            </li>
          ))}
        </ul>

        <dl className="recap__liste">
          <div>
            <dt>Frais de port{commande.libelleLivraison ? ` (${commande.libelleLivraison})` : ''}</dt>
            <dd>{commande.fraisPortTtc > 0 ? formaterPrix(commande.fraisPortTtc) : 'Gratuit'}</dd>
          </div>
          <div className="recap__detail">
            <dt>Total HT</dt>
            <dd>{formaterPrix(commande.totalHt)}</dd>
          </div>
          <div className="recap__detail">
            <dt>TVA</dt>
            <dd>{formaterPrix(commande.totalTva)}</dd>
          </div>
          <div className="recap__total">
            <dt>Total TTC</dt>
            <dd>{formaterPrix(commande.totalTtc)}</dd>
          </div>
        </dl>
      </section>

      <ListeFactures commande={commande} jeton={jeton} />

      <div className="detail-commande__adresses">
        <BlocAdresse titre="Adresse de livraison" adresse={commande.adresseLivraison} />
        <BlocAdresse titre="Adresse de facturation" adresse={commande.adresseFacturation} />
      </div>
    </div>
  )
}
