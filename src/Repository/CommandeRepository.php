<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\Panier;
use App\Entity\Utilisateur;
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

    /**
     * Commandes non payées issues de ce panier (remplacées si le client recommande).
     *
     * @return list<Commande>
     */
    public function commandesEnAttenteDuPanier(Panier $panier): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.panier = :panier')
            ->andWhere('c.statut = :statut')
            ->setParameter('panier', $panier)
            ->setParameter('statut', StatutCommande::EnAttentePaiement)
            ->getQuery()
            ->getResult();
    }

    /**
     * Identifiants des commandes non payées dont la réservation a expiré.
     *
     * @return list<int>
     */
    public function idsExpirees(\DateTimeImmutable $maintenant, int $limite = 100): array
    {
        return array_map('intval', $this->createQueryBuilder('c')
            ->select('c.id')
            ->where('c.statut = :statut')
            ->andWhere('c.expireAt <= :maintenant')
            ->setParameter('statut', StatutCommande::EnAttentePaiement)
            ->setParameter('maintenant', $maintenant)
            ->orderBy('c.expireAt', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getSingleColumnResult());
    }

    /**
     * Commandes d'un client, les plus récentes d'abord.
     *
     * @return list<Commande>
     */
    public function commandesDe(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.lignes', 'l')->addSelect('l')
            ->where('c.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
