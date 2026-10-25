<?php

declare(strict_types=1);

namespace App\State\Commande;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Commande\CommandeVue;
use App\Entity\Utilisateur;
use App\Repository\CommandeRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GET /api/commandes : commandes du client connecté (accès refusé sans session).
 *
 * @implements ProviderInterface<CommandeVue>
 */
final readonly class MesCommandesProvider implements ProviderInterface
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private Security $security,
    ) {
    }

    /**
     * @return list<CommandeVue>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return [];
        }

        return array_map(static fn ($commande): CommandeVue => CommandeVue::depuis($commande), $this->commandeRepository->commandesDe($utilisateur));
    }
}
