<?php

declare(strict_types=1);

namespace App\State\Panier;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Panier\ModificationLigne;
use App\ApiResource\Panier\PanierCalcule;
use App\Entity\LignePanier;
use App\Service\Panier\CalculateurPanier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * PATCH /api/panier/lignes/{id} : nouvelle quantité, ramenée au maximum disponible.
 *
 * @implements ProcessorInterface<ModificationLigne, PanierCalcule>
 */
final readonly class ModificationLigneProcessor implements ProcessorInterface
{
    public function __construct(
        private LigneDuPanierCourant $ligneDuPanierCourant,
        private CalculateurPanier $calculateur,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PanierCalcule
    {
        \assert($data instanceof ModificationLigne && null !== $data->quantite);

        $ligne = $this->ligneDuPanierCourant->trouver($uriVariables);
        $quantiteMax = $ligne->getInstrument()->isPublished() ? LignePanier::quantiteMaximale($ligne->getInstrument()) : 0;
        if (0 === $quantiteMax) {
            throw new UnprocessableEntityHttpException('Cet instrument n\'est plus disponible : retirez-le du panier.');
        }

        $ligne->setQuantite(min($data->quantite, $quantiteMax));
        $ligne->getPanier()->actualiserHorodatage();
        $this->entityManager->flush();

        return $this->calculateur->calculer($ligne->getPanier());
    }
}
