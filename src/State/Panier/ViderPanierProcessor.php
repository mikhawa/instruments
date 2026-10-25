<?php

declare(strict_types=1);

namespace App\State\Panier;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Service\Panier\PanierCourant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * DELETE /api/panier : retire toutes les lignes (réponse 204).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class ViderPanierProcessor implements ProcessorInterface
{
    public function __construct(
        private PanierCourant $panierCourant,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $panier = $this->panierCourant->trouver();
        if (null !== $panier && !$panier->isVide()) {
            $panier->vider();
            $this->entityManager->flush();
        }

        return null;
    }
}
