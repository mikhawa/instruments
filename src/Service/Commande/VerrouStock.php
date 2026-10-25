<?php

declare(strict_types=1);

namespace App\Service\Commande;

use App\Entity\Instrument;
use App\Entity\Stock;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verrouille (SELECT … FOR UPDATE) et relit les stocks des instruments donnés, dans l'ordre
 * des identifiants pour éviter les interblocages. À appeler dans une transaction : deux commandes
 * simultanées d'une même pièce unique sont ainsi sérialisées, la seconde voit le stock à jour.
 */
final readonly class VerrouStock
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param iterable<Instrument|null> $instruments
     */
    public function verrouiller(iterable $instruments): void
    {
        $stocks = [];
        foreach ($instruments as $instrument) {
            $stock = $instrument?->getStock();
            if ($stock instanceof Stock && null !== $stock->getId()) {
                $stocks[$stock->getId()] = $stock;
            }
        }
        ksort($stocks);

        foreach ($stocks as $stock) {
            $this->entityManager->refresh($stock, LockMode::PESSIMISTIC_WRITE);
        }
    }
}
