<?php

declare(strict_types=1);

namespace App\Scheduler\Handler;

use App\Repository\PanierRepository;
use App\Scheduler\Message\PurgerPaniersVisiteurs;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Supprime les paniers sans compte non modifiés depuis N jours
 * (durée de vie du cookie « panier »). Les paniers des clients sont conservés.
 */
#[AsMessageHandler]
final readonly class PurgerPaniersVisiteursHandler
{
    public function __construct(
        private PanierRepository $panierRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgerPaniersVisiteurs $message): int
    {
        $limite = new \DateTimeImmutable(\sprintf('-%d days', $message->joursInactivite), new \DateTimeZone('UTC'));
        $nombre = $this->panierRepository->supprimerPaniersVisiteursInactifsDepuis($limite);
        $this->logger->info('Purge des paniers visiteurs : {nombre} supprimé(s).', ['nombre' => $nombre]);

        return $nombre;
    }
}
