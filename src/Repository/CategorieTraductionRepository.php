<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CategorieTraduction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategorieTraduction>
 */
class CategorieTraductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategorieTraduction::class);
    }
}
