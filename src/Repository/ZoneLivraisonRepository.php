<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ZoneLivraison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZoneLivraison>
 */
class ZoneLivraisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZoneLivraison::class);
    }

    /**
     * Zone contenant ce pays (les zones sont peu nombreuses : filtrage en PHP sur la colonne JSON).
     */
    public function trouverPourPays(string $pays): ?ZoneLivraison
    {
        foreach ($this->findAll() as $zone) {
            if ($zone->contientPays($pays)) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Zone (autre que $exclue) contenant déjà ce pays, pour interdire les doublons entre zones.
     */
    public function trouverAutreZonePour(string $pays, ?ZoneLivraison $exclue): ?ZoneLivraison
    {
        foreach ($this->findAll() as $zone) {
            if ($zone !== $exclue && $zone->contientPays($pays)) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Tous les pays livrables, triés.
     *
     * @return list<string>
     */
    public function paysLivrables(): array
    {
        $pays = array_merge([], ...array_map(static fn (ZoneLivraison $zone): array => $zone->getPays(), $this->findAll()));
        $pays = array_values(array_unique($pays));
        sort($pays);

        return $pays;
    }
}
