<?php

declare(strict_types=1);

namespace App\ApiResource\Paiement;

/**
 * Informations à recopier dans l'application bancaire (ou à scanner : qrEpc).
 */
final readonly class VirementVue
{
    public function __construct(
        public string $beneficiaire,
        /** Groupé par 4 caractères */
        public string $iban,
        public ?string $bic,
        public int $montant,
        /** Communication structurée : +++123/4567/89002+++ */
        public string $communication,
        /** Contenu du QR code EPC, à afficher tel quel en QR code */
        public string $qrEpc,
    ) {
    }
}
