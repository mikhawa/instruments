<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ModeLivraison;
use App\Entity\TarifLivraison;
use App\Entity\ZoneLivraison;
use App\Enum\ClasseLivraison;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Grille de livraison de développement, identique à celle insérée par la migration
 * Version20261025072313 (doctrine:fixtures:load vide toutes les tables, grille comprise).
 */
final class LivraisonFixtures extends Fixture
{
    /** code => [nom, pays] */
    private const ZONES = [
        'BE' => ['Belgique', ['BE']],
        'UE1' => ['Pays voisins', ['DE', 'FR', 'LU', 'NL']],
        'UE2' => ['Reste de l\'Union européenne', ['AT', 'BG', 'CY', 'CZ', 'DK', 'EE', 'ES', 'FI', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LV', 'MT', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK']],
    ];

    /** zone => classe => [prix TTC en centimes, délai min, délai max] */
    private const TARIFS_DOMICILE = [
        'BE' => ['petit_colis' => [690, 1, 3], 'colis' => [990, 1, 3], 'hors_norme' => [3500, 2, 5]],
        'UE1' => ['petit_colis' => [1490, 2, 5], 'colis' => [2290, 2, 5], 'hors_norme' => [6500, 3, 8]],
        'UE2' => ['petit_colis' => [1990, 3, 8], 'colis' => [3290, 3, 8], 'hors_norme' => [8900, 5, 12]],
    ];

    public function load(ObjectManager $manager): void
    {
        $domicile = (new ModeLivraison())
            ->setCode('domicile')
            ->setNom('Livraison à domicile')
            ->setDescription('Colis suivi, remis contre signature, emballage renforcé.')
            ->setPosition(1);
        $retrait = (new ModeLivraison())
            ->setCode('retrait_atelier')
            ->setNom('Retrait à l\'atelier')
            ->setDescription('Gratuit, sur rendez-vous : nous vous contactons dès que l\'instrument est prêt.')
            ->setRetrait(true)
            ->setPosition(2);
        $manager->persist($domicile);
        $manager->persist($retrait);

        foreach (self::ZONES as $code => [$nom, $pays]) {
            $zone = (new ZoneLivraison())->setCode($code)->setNom($nom)->setPays($pays);
            $manager->persist($zone);

            foreach (self::TARIFS_DOMICILE[$code] as $classe => [$prix, $min, $max]) {
                $manager->persist((new TarifLivraison())
                    ->setZone($zone)
                    ->setMode($domicile)
                    ->setClasse(ClasseLivraison::from($classe))
                    ->setPrixTtc($prix)
                    ->setDelaiJoursMin($min)
                    ->setDelaiJoursMax($max));
            }
        }

        $manager->flush();
    }
}
