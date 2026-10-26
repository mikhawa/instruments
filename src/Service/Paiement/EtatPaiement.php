<?php

declare(strict_types=1);

namespace App\Service\Paiement;

use App\Enum\StatutPaiement;

/**
 * Statut d'un paiement tel que le prestataire le rapporte.
 */
final readonly class EtatPaiement
{
    /**
     * @param array<string, mixed> $details informations non sensibles à conserver (libellé et pays de la carte…)
     */
    public function __construct(
        public StatutPaiement $statut,
        /** Montant réellement demandé chez le prestataire, en centimes */
        public int $montant,
        public string $devise,
        public ?string $moyen = null,
        public int $montantRembourse = 0,
        public array $details = [],
    ) {
    }
}
