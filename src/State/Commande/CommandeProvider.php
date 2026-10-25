<?php

declare(strict_types=1);

namespace App\State\Commande;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Commande\CommandeVue;
use App\Repository\CommandeRepository;
use App\Security\Voter\CommandeVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/commandes/{numero}[?jeton=…] : 404 pour toute commande inaccessible,
 * sans distinguer « inexistante » et « pas à vous ».
 *
 * @implements ProviderInterface<CommandeVue>
 */
final readonly class CommandeProvider implements ProviderInterface
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CommandeVue
    {
        $commande = $this->commandeRepository->findOneBy(['numero' => (string) ($uriVariables['numero'] ?? '')]);
        if (null === $commande || !$this->security->isGranted(CommandeVoter::VOIR, $commande)) {
            throw new NotFoundHttpException('Commande introuvable.');
        }

        return CommandeVue::depuis($commande);
    }
}
