// Suivi Matomo du site public, actif uniquement sur le build de production
// (variables définies dans frontend/.env.production, absentes en dev).
const url = import.meta.env.VITE_MATOMO_URL
const siteId = import.meta.env.VITE_MATOMO_SITE_ID
const actif = import.meta.env.PROD && Boolean(url && siteId)

/**
 * Charge matomo.js une seule fois. Pas de trackPageView ici :
 * chaque page (y compris la première) est comptée par suivrePage().
 */
export function initialiserMatomo() {
  if (!actif) return
  const _paq = (window._paq = window._paq || [])
  _paq.push(['enableLinkTracking'])
  _paq.push(['setTrackerUrl', `${url}matomo.php`])
  _paq.push(['setSiteId', siteId])
  const script = document.createElement('script')
  script.async = true
  script.src = `${url}matomo.js`
  document.head.appendChild(script)
}

/** Enregistre une page vue : la SPA ne recharge pas la page lors des navigations. */
export function suivrePage(urlPage, referent) {
  if (!actif) return
  const _paq = window._paq
  if (referent) _paq.push(['setReferrerUrl', referent])
  _paq.push(['setCustomUrl', urlPage])
  _paq.push(['setDocumentTitle', document.title])
  _paq.push(['trackPageView'])
}
