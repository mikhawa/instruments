<?php

declare(strict_types=1);

namespace App\State\Panier;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Panier\PanierCalcule;
use App\Service\Panier\CalculateurPanier;
use App\Service\Panier\PanierCourant;

/**
 * GET /api/panier : panier courant recalculé (vide si le visiteur n'en a pas encore).
 *
 * @implements ProviderInterface<PanierCalcule>
 */
final readonly class PanierProvider implements ProviderInterface
{
    public function __construct(
        private PanierCourant $panierCourant,
        private CalculateurPanier $calculateur,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PanierCalcule
    {
        return $this->calculateur->calculer($this->panierCourant->trouver());
    }
}
