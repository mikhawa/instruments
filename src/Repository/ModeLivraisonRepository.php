<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ModeLivraison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModeLivraison>
 */
class ModeLivraisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModeLivraison::class);
    }

    /**
     * Modes de retrait sur place actifs (gratuits, sans grille).
     *
     * @return list<ModeLivraison>
     */
    public function modesRetraitActifs(): array
    {
        return $this->findBy(['retrait' => true, 'active' => true], ['position' => 'ASC']);
    }
}
