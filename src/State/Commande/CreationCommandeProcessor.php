<?php

declare(strict_types=1);

namespace App\State\Commande;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Commande\CommandeVue;
use App\ApiResource\Commande\NouvelleCommande;
use App\Service\Commande\CommandeRefuseeException;
use App\Service\Commande\CreationCommande;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * POST /api/commandes → 201 et la commande créée (avec le jeton du lien de suivi).
 * 409 si le panier a changé (le client doit le revoir), 422 si la demande est incomplète.
 *
 * @implements ProcessorInterface<NouvelleCommande, CommandeVue>
 */
final readonly class CreationCommandeProcessor implements ProcessorInterface
{
    public function __construct(
        private VueCommande $vueCommande,
        private CreationCommande $creationCommande,
        #[Autowire(service: 'limiter.creation_commande')]
        private RateLimiterFactoryInterface $creationCommandeLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CommandeVue
    {
        \assert($data instanceof NouvelleCommande);
        $requete = $context['request'] ?? null;
        \assert($requete instanceof Request);

        $limite = $this->creationCommandeLimiter->create($requete->getClientIp())->consume();
        if (!$limite->isAccepted()) {
            throw new TooManyRequestsHttpException($limite->getRetryAfter()->getTimestamp() - time(), 'Trop de commandes en peu de temps, réessayez plus tard.');
        }

        $cle = $requete->headers->get('Idempotency-Key');
        if (null !== $cle && 1 !== preg_match('/^[A-Za-z0-9_-]{8,64}$/', $cle)) {
            throw new BadRequestHttpException('En-tête Idempotency-Key invalide (8 à 64 caractères alphanumériques, « - » ou « _ »).');
        }

        try {
            $commande = $this->creationCommande->creer($data, $cle);
        } catch (CommandeRefuseeException $e) {
            throw $e->conflit ? new ConflictHttpException($e->getMessage(), $e) : new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->vueCommande->pour($commande, avecJeton: true);
    }
}
