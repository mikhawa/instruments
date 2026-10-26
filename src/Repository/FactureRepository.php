<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\Facture;
use App\Enum\TypeFacture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facture>
 */
class FactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    /**
     * Facture (et non note de crédit) d'une commande : une seule par commande.
     */
    public function factureDe(Commande $commande): ?Facture
    {
        return $this->findOneBy(['commande' => $commande, 'type' => TypeFacture::Facture]);
    }
}
