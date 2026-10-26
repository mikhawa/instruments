<?php

declare(strict_types=1);

namespace App\Service\Paiement;

/**
 * Demande de paiement impossible pour cette commande (déjà payée, réservation expirée,
 * moyen non proposé…) : message destiné au client.
 */
final class PaiementRefuseException extends \RuntimeException
{
}
