<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Remboursement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Remboursement>
 */
class RemboursementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Remboursement::class);
    }

    /**
     * Remboursements décidés mais pas encore acceptés par le prestataire.
     *
     * @return list<int>
     */
    public function idsAEffectuer(int $limite = 50): array
    {
        return array_map('intval', $this->createQueryBuilder('r')
            ->select('r.id')
            ->where('r.statut = :statut')
            ->setParameter('statut', Remboursement::STATUT_A_EFFECTUER)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getSingleColumnResult());
    }
}
