<?php

declare(strict_types=1);

namespace App\State\Commande;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Security\Voter\CommandeVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Commande désignée par son numéro dans l'URL, si le demandeur y a accès (CommandeVoter :
 * son client, un administrateur, ou le jeton du lien de suivi). Sinon 404, sans distinguer
 * « inexistante » et « pas à vous ».
 */
final readonly class CommandeDuClient
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    public function trouver(array $uriVariables): Commande
    {
        $commande = $this->commandeRepository->findOneBy(['numero' => (string) ($uriVariables['numero'] ?? '')]);
        if (null === $commande || !$this->security->isGranted(CommandeVoter::VOIR, $commande)) {
            throw new NotFoundHttpException('Commande introuvable.');
        }

        return $commande;
    }
}
