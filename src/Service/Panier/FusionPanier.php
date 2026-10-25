<?php

declare(strict_types=1);

namespace App\Service\Panier;

use App\Entity\LignePanier;
use App\Entity\Panier;
use App\Entity\Utilisateur;
use App\Repository\PanierRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rattache le panier d'un visiteur au client qui vient de se connecter.
 *
 *  - client sans panier : le panier du visiteur devient le sien ;
 *  - les deux ont un panier : lignes fusionnées, même instrument → quantité la plus grande
 *    (pas la somme), dans la limite de LignePanier::quantiteMaximale() ;
 *  - panier visiteur vide : celui du client est conservé.
 */
final readonly class FusionPanier
{
    public function __construct(
        private PanierRepository $panierRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Renvoie le panier du client après la fusion (null s'il n'en a pas et que le panier visiteur est vide).
     */
    public function fusionner(Panier $panierVisiteur, Utilisateur $utilisateur): ?Panier
    {
        $panierClient = $this->panierRepository->trouverPanierUtilisateur($utilisateur);

        if ($panierVisiteur->isVide()) {
            $this->entityManager->remove($panierVisiteur);
            $this->entityManager->flush();

            return $panierClient;
        }

        if (null === $panierClient) {
            $panierVisiteur->setUtilisateur($utilisateur);
            $this->entityManager->flush();

            return $panierVisiteur;
        }

        foreach ($panierVisiteur->getLignes() as $ligneVisiteur) {
            $instrument = $ligneVisiteur->getInstrument();
            $quantite = $ligneVisiteur->getQuantite();
            $ligneClient = $panierClient->getLignePour($instrument);

            if (null !== $ligneClient) {
                $quantite = max($quantite, $ligneClient->getQuantite());
            }
            // Pièce unique ou stock réduit ; une ligne indisponible garde sa quantité (alerte affichée)
            $quantiteMax = LignePanier::quantiteMaximale($instrument);
            if ($quantiteMax > 0) {
                $quantite = min($quantite, $quantiteMax);
            }

            if (null !== $ligneClient) {
                $ligneClient->setQuantite($quantite);
            } else {
                $this->entityManager->persist(new LignePanier($panierClient, $instrument, $quantite));
            }
        }
        $panierClient->actualiserHorodatage();

        $this->entityManager->remove($panierVisiteur);
        $this->entityManager->flush();

        return $panierClient;
    }
}
