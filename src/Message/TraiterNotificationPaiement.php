<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Notification d'un prestataire à traiter en arrière-plan (transport « async ») :
 * le webhook répond tout de suite, le statut est relu ensuite.
 */
final readonly class TraiterNotificationPaiement
{
    public function __construct(public int $evenementId)
    {
    }
}
