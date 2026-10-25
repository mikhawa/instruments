<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TarifLivraison;
use App\Entity\ZoneLivraison;
use App\Enum\ClasseLivraison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TarifLivraison>
 */
class TarifLivraisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TarifLivraison::class);
    }

    /**
     * Tarifs des modes actifs pour une zone et une classe, dans l'ordre d'affichage des modes.
     *
     * @return list<TarifLivraison>
     */
    public function tarifsPour(ZoneLivraison $zone, ClasseLivraison $classe): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.mode', 'm')->addSelect('m')
            ->where('t.zone = :zone')
            ->andWhere('t.classe = :classe')
            ->andWhere('m.active = true')
            ->andWhere('m.retrait = false')
            ->setParameter('zone', $zone)
            ->setParameter('classe', $classe)
            ->orderBy('m.position', 'ASC')
            ->addOrderBy('t.prixTtc', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
