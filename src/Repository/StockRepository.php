<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    /**
     * Nombre d'instruments dont la quantité disponible est au niveau ou sous le seuil d'alerte.
     */
    public function compterSousSeuilAlerte(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.quantite - s.quantiteReservee <= s.seuilAlerte')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
