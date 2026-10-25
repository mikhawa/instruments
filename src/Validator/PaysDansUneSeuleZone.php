<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Chaque pays d'une zone de livraison ne doit appartenir à aucune autre zone.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class PaysDansUneSeuleZone extends Constraint
{
    public string $message = 'Le pays {{ pays }} appartient déjà à la zone « {{ zone }} ».';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
