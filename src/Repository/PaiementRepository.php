<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\Paiement;
use App\Enum\PrestatairePaiement;
use App\Enum\StatutPaiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paiement>
 */
class PaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paiement::class);
    }

    /**
     * Paiement en ligne encore ouvert d'une commande : sa page de paiement est réutilisée
     * plutôt que d'en créer un second (double clic, retour arrière du navigateur).
     */
    public function enLigneOuvert(Commande $commande, PrestatairePaiement $prestataire): ?Paiement
    {
        return $this->createQueryBuilder('p')
            ->where('p.commande = :commande')
            ->andWhere('p.prestataire = :prestataire')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.urlPaiement IS NOT NULL')
            ->setParameter('commande', $commande)
            ->setParameter('prestataire', $prestataire)
            ->setParameter('statut', StatutPaiement::Ouvert)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Paiements en ligne d'une commande dont le statut peut encore changer.
     *
     * @return list<Paiement>
     */
    public function enLigneEnCoursDe(Commande $commande): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.commande = :commande')
            ->andWhere('p.prestataire != :virement')
            ->andWhere('p.statut IN (:statuts)')
            ->andWhere('p.idExterne IS NOT NULL')
            ->setParameter('commande', $commande)
            ->setParameter('virement', PrestatairePaiement::Virement)
            ->setParameter('statuts', [StatutPaiement::Ouvert, StatutPaiement::EnAttente, StatutPaiement::Autorise])
            ->getQuery()
            ->getResult();
    }

    /**
     * Identifiants des paiements en ligne à relire chez le prestataire (filet de sécurité
     * si une notification s'est perdue) : en cours, créés entre $depuis et $avant.
     *
     * @return list<int>
     */
    public function idsEnLigneASynchroniser(\DateTimeImmutable $depuis, \DateTimeImmutable $avant, int $limite = 100): array
    {
        return array_map('intval', $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.prestataire != :virement')
            ->andWhere('p.statut IN (:statuts)')
            ->andWhere('p.idExterne IS NOT NULL')
            ->andWhere('p.createdAt BETWEEN :depuis AND :avant')
            ->setParameter('virement', PrestatairePaiement::Virement)
            ->setParameter('statuts', [StatutPaiement::Ouvert, StatutPaiement::EnAttente, StatutPaiement::Autorise])
            ->setParameter('depuis', $depuis)
            ->setParameter('avant', $avant)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getSingleColumnResult());
    }
}
