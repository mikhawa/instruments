<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Commande;
use App\Enum\StatutCommande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Nombre de commandes dans l'un des statuts donnés.
     *
     * @param list<StatutCommande> $statuts
     */
    public function compterParStatuts(array $statuts): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statut IN (:statuts)')
            ->setParameter('statuts', $statuts)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
