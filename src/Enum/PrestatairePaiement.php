<?php

declare(strict_types=1);

namespace App\Enum;

enum PrestatairePaiement: string
{
    /** Paiement en ligne hébergé (Bancontact, cartes, PayPal…) */
    case Mollie = 'mollie';
    /** Faux prestataire du développement et des tests : jamais en production */
    case Simulation = 'simulation';
    /** Virement SEPA direct, validé à la main dans le back-office */
    case Virement = 'virement';

    public function isEnLigne(): bool
    {
        return self::Virement !== $this;
    }
}
