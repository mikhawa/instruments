<?php

declare(strict_types=1);

namespace App\Service\Paiement;

final readonly class PaiementCree
{
    public function __construct(
        public string $idExterne,
        /** Page de paiement hébergée par le prestataire */
        public string $urlPaiement,
    ) {
    }
}
