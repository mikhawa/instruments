<?php

declare(strict_types=1);

namespace App\State\Panier;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Panier\PanierCalcule;
use App\Service\Panier\CalculateurPanier;
use Doctrine\ORM\EntityManagerInterface;

/**
 * DELETE /api/panier/lignes/{id} : retire la ligne et renvoie le panier recalculé.
 *
 * @implements ProcessorInterface<mixed, PanierCalcule>
 */
final readonly class SuppressionLigneProcessor implements ProcessorInterface
{
    public function __construct(
        private LigneDuPanierCourant $ligneDuPanierCourant,
        private CalculateurPanier $calculateur,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PanierCalcule
    {
        $ligne = $this->ligneDuPanierCourant->trouver($uriVariables);
        $panier = $ligne->getPanier();
        $panier->retirerLigne($ligne);
        $this->entityManager->flush();

        return $this->calculateur->calculer($panier);
    }
}
