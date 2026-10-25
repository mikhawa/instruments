<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Scheduler\Message\PurgerPaniersVisiteurs;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Programme des tâches récurrentes (transport « scheduler_default »).
 * Il est exécuté par le worker : messenger:consume async scheduler_default.
 */
#[AsSchedule('default')]
final class ProgrammeTaches implements ScheduleProviderInterface
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            // Mémorise la dernière exécution dans le cache applicatif : les tâches
            // manquées pendant un arrêt du worker sont rattrapées au redémarrage
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)

            // Chaque nuit à 3 h 30 (heure de Bruxelles), en dehors des heures d'affluence
            ->add(RecurringMessage::every('1 day', new PurgerPaniersVisiteurs(), from: new \DateTimeImmutable('03:30', new \DateTimeZone('Europe/Brussels'))))

            // À venir :
            //  - expiration des commandes non payées (toutes les 5 minutes) ;
            //  - relance des paniers abandonnés.
        ;
    }
}
