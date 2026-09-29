<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InstrumentTraduction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InstrumentTraduction>
 */
class InstrumentTraductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstrumentTraduction::class);
    }
}
