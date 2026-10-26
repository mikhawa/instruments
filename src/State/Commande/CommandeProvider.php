<?php

declare(strict_types=1);

namespace App\State\Commande;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Commande\CommandeVue;

/**
 * GET /api/commandes/{numero}[?jeton=…] : 404 pour toute commande inaccessible,
 * sans distinguer « inexistante » et « pas à vous ».
 *
 * @implements ProviderInterface<CommandeVue>
 */
final readonly class CommandeProvider implements ProviderInterface
{
    public function __construct(
        private CommandeDuClient $commandeDuClient,
        private VueCommande $vueCommande,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CommandeVue
    {
        return $this->vueCommande->pour($this->commandeDuClient->trouver($uriVariables));
    }
}
