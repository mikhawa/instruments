<?php

declare(strict_types=1);

namespace App\Scheduler\Message;

/**
 * Tâche planifiée : suppression des paniers de visiteurs inactifs.
 */
final readonly class PurgerPaniersVisiteurs
{
    public function __construct(public int $joursInactivite = 30)
    {
    }
}
