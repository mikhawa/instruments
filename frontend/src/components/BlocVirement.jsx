import { useEffect, useState } from 'react'
import { formaterPrix } from '../lib/format.js'
import { formaterDateHeure } from '../lib/commande.js'

/**
 * Bouton « Copier » : copie la valeur dans le presse-papiers et le confirme à voix haute.
 */
function BoutonCopier({ valeur, libelle }) {
  const [copie, setCopie] = useState(false)

  async function copier() {
    try {
      await navigator.clipboard.writeText(valeur)
      setCopie(true)
    } catch {
      // Presse-papiers indisponible : la valeur reste visible et sélectionnable
    }
  }

  return (
    <button type="button" className="bouton bouton--discret" onClick={copier} aria-label={`Copier ${libelle}`}>
      {copie ? 'Copié' : 'Copier'}
    </button>
  )
}

/**
 * QR code EPC (scannable par les applications bancaires), généré dans le navigateur.
 * La bibliothèque n'est chargée que lorsqu'un virement est affiché.
 */
function QrEpc({ contenu }) {
  const [image, setImage] = useState(null)

  useEffect(() => {
    let actif = true
    import('qrcode')
      .then((module) => (module.default ?? module).toDataURL(contenu, { errorCorrectionLevel: 'M', margin: 2, width: 240 }))
      .then((url) => {
        if (actif) setImage(url)
      })
      .catch(() => {
        // Sans QR code, les coordonnées restent utilisables à la main
      })

    return () => {
      actif = false
    }
  }, [contenu])

  if (!image) return null

  return (
    <img
      className="virement__qr"
      src={image}
      width="240"
      height="240"
      alt="QR code de virement à scanner avec l'application de votre banque"
    />
  )
}

/**
 * Coordonnées de virement d'une commande, avec QR code EPC.
 *
 * @param {{ virement: object, expireAt?: string }} props
 */
export default function BlocVirement({ virement, expireAt }) {
  return (
    <section className="virement" aria-labelledby="titre-virement">
      <h2 id="titre-virement">Payer par virement</h2>
      <p className="commande__intro">
        Effectuez le virement ci-dessous
        {expireAt && <> avant le <strong>{formaterDateHeure(expireAt)}</strong></>}. Vos instruments sont réservés
        jusque-là ; la commande passe en « Payée » dès réception des fonds.
      </p>

      <div className="virement__corps">
        <dl className="virement__liste">
          <div>
            <dt>Bénéficiaire</dt>
            <dd>{virement.beneficiaire}</dd>
          </div>
          <div>
            <dt>IBAN</dt>
            <dd>
              <span className="virement__valeur">{virement.iban}</span>
              <BoutonCopier valeur={virement.iban.replace(/\s+/g, '')} libelle="l'IBAN" />
            </dd>
          </div>
          {virement.bic && (
            <div>
              <dt>BIC</dt>
              <dd>
                <span className="virement__valeur">{virement.bic}</span>
              </dd>
            </div>
          )}
          <div>
            <dt>Montant</dt>
            <dd>
              <span className="virement__valeur">{formaterPrix(virement.montant)}</span>
            </dd>
          </div>
          <div>
            <dt>Communication</dt>
            <dd>
              <span className="virement__valeur">{virement.communication}</span>
              <BoutonCopier valeur={virement.communication} libelle="la communication" />
            </dd>
          </div>
        </dl>

        {virement.qrEpc && <QrEpc contenu={virement.qrEpc} />}
      </div>

      <p className="commande__alerte" role="note">
        <strong>Indiquez exactement cette communication</strong> : elle nous permet de reconnaître votre paiement.
        Les coordonnées vous ont aussi été envoyées par e-mail.
      </p>
    </section>
  )
}
