<?php

declare(strict_types=1);

namespace App\ApiResource\Commande;

/**
 * Facture ou note de crédit dans le détail d'une commande ; le PDF est à
 * /api/commandes/{numero}/factures/{numero de la facture}.pdf (avec ?jeton= pour un invité).
 */
final readonly class FactureVue
{
    public function __construct(
        public string $numero,
        /** « facture » ou « note_credit » */
        public string $type,
        public \DateTimeImmutable $dateEmission,
        public int $totalTtc,
    ) {
    }
}
