<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * État physique d'un instrument proposé à la vente.
 */
enum EtatInstrument: string
{
    case Neuf = 'neuf';
    case Occasion = 'occasion';
    case Ancien = 'ancien';
    case Restaure = 'restaure';
}
