<?php

declare(strict_types=1);

namespace App\Service\Paiement\Virement;

use App\Entity\Paiement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Compte bancaire du magasin pour le virement SEPA direct, et règles d'accès à ce moyen
 * (prepare/03-paiement.md § 6) : proposé à partir d'un seuil, si l'IBAN est configuré.
 */
final readonly class CoordonneesVirement
{
    public function __construct(
        #[Autowire(env: 'VIREMENT_IBAN')]
        private string $iban,
        #[Autowire(env: 'VIREMENT_BIC')]
        private string $bic,
        #[Autowire(env: 'VIREMENT_BENEFICIAIRE')]
        private string $beneficiaire,
        /** Montant TTC minimal en centimes */
        #[Autowire(param: 'app.virement_seuil')]
        private int $seuil,
    ) {
    }

    public function isConfigure(): bool
    {
        return 1 === preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $this->getIban()) && '' !== trim($this->beneficiaire);
    }

    public function isDisponiblePour(int $totalTtc): bool
    {
        return $this->isConfigure() && $totalTtc >= $this->seuil;
    }

    public function getSeuil(): int
    {
        return $this->seuil;
    }

    /**
     * IBAN sans espaces, en majuscules.
     */
    public function getIban(): string
    {
        return strtoupper(preg_replace('/\s+/', '', $this->iban) ?? '');
    }

    /**
     * IBAN groupé par 4 caractères, pour l'affichage.
     */
    public function getIbanLisible(): string
    {
        return trim(chunk_split($this->getIban(), 4, ' '));
    }

    public function getBic(): string
    {
        return strtoupper(trim($this->bic));
    }

    public function getBeneficiaire(): string
    {
        return trim($this->beneficiaire);
    }

    /**
     * Contenu du QR code EPC (« SEPA Credit Transfer », version 002), lu par les applications bancaires.
     * La communication structurée belge va dans la zone libre : les banques belges la reconnaissent.
     */
    public function contenuQrEpc(Paiement $paiement): string
    {
        return implode("\n", [
            'BCD',
            '002',
            '1',
            'SCT',
            $this->getBic(),
            mb_substr($this->getBeneficiaire(), 0, 70),
            $this->getIban(),
            \sprintf('EUR%d.%02d', intdiv($paiement->getMontant(), 100), $paiement->getMontant() % 100),
            '',
            '',
            (string) $paiement->getReferenceVirement(),
        ]);
    }
}
