<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Commande;
use App\Entity\Instrument;
use App\Entity\LigneCommande;
use App\Entity\MouvementStock;
use App\Entity\Utilisateur;
use App\Enum\StatutCommande;
use App\Enum\TypeMouvementStock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Commandes de développement couvrant chaque statut du cycle de vie.
 * Le stock et le journal des mouvements sont mis à jour comme le fera le Workflow :
 * réservation à la commande, sortie à l'expédition, libération à l'annulation.
 */
final class CommandeFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [UtilisateurFixtures::class, CatalogueFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // [client, statut, [référence => quantité], frais de port TTC (centimes), n° de suivi]
        $commandes = [
            [UtilisateurFixtures::MARIE, StatutCommande::Livree, ['PRC-BOD-0013' => 1, 'PRC-BEN-0014' => 2], 1500, 'BE123456789'],
            [UtilisateurFixtures::JAN, StatutCommande::Expediee, ['VNT-SHK-0012' => 1], 1500, 'BE987654321'],
            [UtilisateurFixtures::LUCIA, StatutCommande::Payee, ['VNT-GAI-0010' => 1], 2500, null],
            [UtilisateurFixtures::MARIE, StatutCommande::EnAttentePaiement, ['VNT-DUD-0011' => 2], 1500, null],
            [UtilisateurFixtures::JAN, StatutCommande::Annulee, ['CRD-SAZ-0002' => 1], 1500, null],
        ];

        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach ($commandes as $index => [$refClient, $statut, $articles, $fraisPort, $suivi]) {
            $client = $this->getReference($refClient, Utilisateur::class);
            $adresse = $client->getAdresses()->first();

            $commande = (new Commande())
                ->setNumero(\sprintf('CMD-%s-%06d', $maintenant->format('Y'), $index + 1))
                ->setUtilisateur($client)
                ->setLocale($client->getLocale())
                ->setAdresseLivraison($adresse)
                ->setAdresseFacturation($adresse)
                ->setNumeroSuivi($suivi);

            foreach ($articles as $reference => $quantite) {
                $instrument = $this->getReference(CatalogueFixtures::INSTRUMENT.$reference, Instrument::class);
                $commande->addLigne(new LigneCommande($instrument, $quantite, $client->getLocale()));
                $this->appliquerStock($manager, $commande, $instrument, $quantite, $statut);
            }

            $commande->setFraisPortTtc($fraisPort);
            $this->horodater($commande, $statut, $maintenant);
            $commande->setStatut($statut);

            $manager->persist($commande);
        }

        $manager->flush();
    }

    /**
     * Rejoue les effets de stock des transitions déjà franchies par la commande.
     */
    private function appliquerStock(ObjectManager $manager, Commande $commande, Instrument $instrument, int $quantite, StatutCommande $statut): void
    {
        $stock = $instrument->getStock();

        $stock->reserver($quantite);
        $manager->persist(new MouvementStock($instrument, TypeMouvementStock::Reservation, -$quantite, $commande));

        if (\in_array($statut, [StatutCommande::Expediee, StatutCommande::Livree], true)) {
            $stock->sortir($quantite);
            $manager->persist(new MouvementStock($instrument, TypeMouvementStock::Sortie, -$quantite, $commande));
        }

        if (StatutCommande::Annulee === $statut) {
            $stock->liberer($quantite);
            $manager->persist(new MouvementStock($instrument, TypeMouvementStock::Liberation, $quantite, $commande, commentaire: 'Annulation client'));
        }
    }

    private function horodater(Commande $commande, StatutCommande $statut, \DateTimeImmutable $maintenant): void
    {
        match ($statut) {
            StatutCommande::Livree => $commande
                ->setPaidAt($maintenant->modify('-10 days'))
                ->setShippedAt($maintenant->modify('-8 days'))
                ->setDeliveredAt($maintenant->modify('-5 days')),
            StatutCommande::Expediee => $commande
                ->setPaidAt($maintenant->modify('-3 days'))
                ->setShippedAt($maintenant->modify('-1 day')),
            StatutCommande::Payee => $commande->setPaidAt($maintenant->modify('-2 hours')),
            StatutCommande::Annulee => $commande->setCancelledAt($maintenant->modify('-1 day')),
            default => null,
        };
    }
}
