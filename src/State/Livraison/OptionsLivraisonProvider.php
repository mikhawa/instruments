<?php

declare(strict_types=1);

namespace App\State\Livraison;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Livraison\OptionsLivraison;
use App\Service\Livraison\CalculateurLivraison;
use App\Service\Panier\PanierCourant;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<OptionsLivraison>
 */
final readonly class OptionsLivraisonProvider implements ProviderInterface
{
    public function __construct(
        private PanierCourant $panierCourant,
        private CalculateurLivraison $calculateurLivraison,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): OptionsLivraison
    {
        $requete = $context['request'] ?? null;
        $pays = $requete instanceof Request ? $requete->query->getString('pays') : '';

        return $this->calculateurLivraison->options($this->panierCourant->trouver(), 1 === preg_match('/^[A-Za-z]{2}$/', $pays) ? $pays : null);
    }
}
