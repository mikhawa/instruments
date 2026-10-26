<?php

declare(strict_types=1);

namespace App\Scheduler\Message;

/**
 * Tâche planifiée : relecture des paiements en ligne restés en cours (notification perdue)
 * et relance des remboursements non envoyés.
 */
final readonly class SynchroniserPaiements
{
}
