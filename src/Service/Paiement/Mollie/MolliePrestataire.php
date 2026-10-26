<?php

declare(strict_types=1);

namespace App\Service\Paiement\Mollie;

use App\Entity\Paiement;
use App\Entity\Remboursement;
use App\Enum\PrestatairePaiement;
use App\Enum\StatutPaiement;
use App\Service\Paiement\EtatPaiement;
use App\Service\Paiement\PaiementCree;
use App\Service\Paiement\PrestataireIndisponibleException;
use App\Service\Paiement\PrestatairePaiementInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Mollie, API Payments v2, en paiement hébergé (prepare/03-paiement.md, D5 et D6).
 * Appels directs avec HttpClient plutôt que le SDK : trois points d'accès suffisent,
 * et les tests utilisent MockHttpClient.
 *
 * La notification de Mollie ne contient que l'identifiant du paiement : le statut est
 * toujours relu ici, ce qui garantit son authenticité sans signature.
 */
final readonly class MolliePrestataire implements PrestatairePaiementInterface
{
    private const API = 'https://api.mollie.com/v2/';

    private const STATUTS = [
        'open' => StatutPaiement::Ouvert,
        'pending' => StatutPaiement::EnAttente,
        'authorized' => StatutPaiement::Autorise,
        'paid' => StatutPaiement::Paye,
        'canceled' => StatutPaiement::Annule,
        'expired' => StatutPaiement::Expire,
        'failed' => StatutPaiement::Echoue,
    ];

    private const LOCALES = ['fr' => 'fr_BE', 'nl' => 'nl_BE', 'en' => 'en_GB', 'de' => 'de_DE', 'es' => 'es_ES'];

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'MOLLIE_API_KEY')]
        private string $cleApi,
    ) {
    }

    public function code(): PrestatairePaiement
    {
        return PrestatairePaiement::Mollie;
    }

    public function isConfigure(): bool
    {
        return 1 === preg_match('/^(test|live)_\w{20,}$/', $this->cleApi);
    }

    public function creer(Paiement $paiement, string $urlRetour, ?string $urlNotification): PaiementCree
    {
        $commande = $paiement->getCommande();
        $corps = [
            'amount' => self::montant($paiement->getMontant(), $paiement->getDevise()),
            'description' => \sprintf('Commande %s', $commande->getNumero()),
            'redirectUrl' => $urlRetour,
            'locale' => self::LOCALES[$commande->getLocale()] ?? 'fr_BE',
            'metadata' => ['commande' => $commande->getNumero(), 'paiement' => $paiement->getId()],
        ];
        if (null !== $urlNotification) {
            $corps['webhookUrl'] = $urlNotification;
        }

        $reponse = $this->appeler('POST', 'payments', $corps);
        $id = $reponse['id'] ?? null;
        $url = $reponse['_links']['checkout']['href'] ?? null;
        if (!\is_string($id) || !\is_string($url)) {
            throw new PrestataireIndisponibleException('Réponse de Mollie incomplète à la création du paiement.');
        }

        return new PaiementCree($id, $url);
    }

    public function etat(Paiement $paiement): EtatPaiement
    {
        $reponse = $this->appeler('GET', 'payments/'.rawurlencode((string) $paiement->getIdExterne()));
        $statut = self::STATUTS[$reponse['status'] ?? ''] ?? throw new PrestataireIndisponibleException(\sprintf('Statut Mollie inconnu : « %s ».', (string) ($reponse['status'] ?? '')));
        $montant = self::centimes($reponse['amount']['value'] ?? null);
        $rembourse = isset($reponse['amountRefunded']['value']) ? self::centimes($reponse['amountRefunded']['value']) : 0;

        if (StatutPaiement::Paye === $statut && $rembourse > 0) {
            $statut = $rembourse >= $montant ? StatutPaiement::Rembourse : StatutPaiement::PartiellementRembourse;
        }

        // Seules des informations non sensibles sont gardées (jamais de numéro de carte ni d'IBAN)
        $details = array_filter([
            'carte' => $reponse['details']['cardLabel'] ?? null,
            'paysCarte' => $reponse['details']['cardCountryCode'] ?? null,
            'motifEchec' => $reponse['details']['failureReason'] ?? null,
            'mode' => $reponse['mode'] ?? null,
        ], static fn ($valeur): bool => \is_string($valeur) && '' !== $valeur);

        return new EtatPaiement(
            statut: $statut,
            montant: $montant,
            devise: (string) ($reponse['amount']['currency'] ?? ''),
            moyen: \is_string($reponse['method'] ?? null) ? $reponse['method'] : null,
            montantRembourse: $rembourse,
            details: $details,
        );
    }

    public function rembourser(Remboursement $remboursement): array
    {
        $paiement = $remboursement->getPaiement();
        $reponse = $this->appeler('POST', 'payments/'.rawurlencode((string) $paiement->getIdExterne()).'/refunds', [
            'amount' => self::montant($remboursement->getMontant(), $paiement->getDevise()),
            'description' => mb_substr($remboursement->getMotif(), 0, 140),
            'metadata' => ['remboursement' => $remboursement->getId()],
        ]);
        $id = $reponse['id'] ?? null;
        if (!\is_string($id)) {
            throw new PrestataireIndisponibleException('Réponse de Mollie incomplète au remboursement.');
        }

        return [$id, 'refunded' === ($reponse['status'] ?? null)];
    }

    /**
     * 1234 centimes → {"currency": "EUR", "value": "12.34"} (format exigé par Mollie).
     *
     * @return array{currency: string, value: string}
     */
    public static function montant(int $centimes, string $devise): array
    {
        return ['currency' => $devise, 'value' => \sprintf('%d.%02d', intdiv($centimes, 100), $centimes % 100)];
    }

    /**
     * "12.34" → 1234, sans passer par un nombre à virgule flottante.
     */
    public static function centimes(mixed $valeur): int
    {
        if (!\is_string($valeur) || 1 !== preg_match('/^(\d+)\.(\d{2})$/', $valeur, $morceaux)) {
            throw new PrestataireIndisponibleException('Montant Mollie illisible.');
        }

        return (int) $morceaux[1] * 100 + (int) $morceaux[2];
    }

    /**
     * @param array<string, mixed>|null $corps
     *
     * @return array<string, mixed>
     */
    private function appeler(string $methode, string $chemin, ?array $corps = null): array
    {
        if (!$this->isConfigure()) {
            throw new PrestataireIndisponibleException('Clé API Mollie absente ou invalide (MOLLIE_API_KEY).');
        }

        try {
            $reponse = $this->httpClient->request($methode, self::API.$chemin, array_filter([
                'auth_bearer' => $this->cleApi,
                'json' => $corps,
                'timeout' => 10,
            ], static fn ($valeur): bool => null !== $valeur));
            $statut = $reponse->getStatusCode();
            $contenu = $reponse->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new PrestataireIndisponibleException('Mollie est injoignable : '.$e->getMessage(), previous: $e);
        }

        if ($statut >= 300) {
            // Message d'erreur de Mollie (jamais la clé) : utile dans les journaux
            throw new PrestataireIndisponibleException(\sprintf('Mollie a refusé la demande (%d) : %s', $statut, (string) ($contenu['detail'] ?? $contenu['title'] ?? 'erreur inconnue')), definitif: $statut >= 400 && $statut < 500 && 429 !== $statut);
        }

        return $contenu;
    }
}
