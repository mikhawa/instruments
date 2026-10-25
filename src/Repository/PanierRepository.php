<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Panier;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Panier>
 */
class PanierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Panier::class);
    }

    /**
     * Panier de visiteur (sans utilisateur) correspondant au jeton du cookie.
     */
    public function trouverPanierVisiteur(Uuid $jeton): ?Panier
    {
        return $this->createQueryBuilder('p')
            ->where('p.jeton = :jeton')
            ->andWhere('p.utilisateur IS NULL')
            ->setParameter('jeton', $jeton, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function trouverPanierUtilisateur(Utilisateur $utilisateur): ?Panier
    {
        return $this->findOneBy(['utilisateur' => $utilisateur]);
    }

    /**
     * Supprime les paniers de visiteurs inactifs depuis la date donnée
     * (les lignes suivent par ON DELETE CASCADE). Renvoie le nombre de paniers supprimés.
     */
    public function supprimerPaniersVisiteursInactifsDepuis(\DateTimeImmutable $limite): int
    {
        return (int) $this->createQueryBuilder('p')
            ->delete()
            ->where('p.utilisateur IS NULL')
            ->andWhere('p.updatedAt < :limite')
            ->setParameter('limite', $limite)
            ->getQuery()
            ->execute();
    }
}
