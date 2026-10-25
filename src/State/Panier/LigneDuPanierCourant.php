<?php

declare(strict_types=1);

namespace App\State\Panier;

use App\Entity\LignePanier;
use App\Repository\LignePanierRepository;
use App\Security\Voter\PanierVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ligne désignée par l'URL, à condition qu'elle appartienne au panier du demandeur.
 * Une ligne d'un autre panier répond 404, comme une ligne inexistante (aucune fuite d'information).
 */
final readonly class LigneDuPanierCourant
{
    public function __construct(
        private LignePanierRepository $lignePanierRepository,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    public function trouver(array $uriVariables): LignePanier
    {
        $ligne = $this->lignePanierRepository->find((int) ($uriVariables['id'] ?? 0));
        if (null === $ligne || !$this->security->isGranted(PanierVoter::ACCES, $ligne->getPanier())) {
            throw new NotFoundHttpException('Ligne de panier introuvable.');
        }

        return $ligne;
    }
}
