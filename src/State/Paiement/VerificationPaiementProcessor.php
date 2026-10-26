<?php

declare(strict_types=1);

namespace App\State\Paiement;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Commande\CommandeVue;
use App\Service\Paiement\PrestataireIndisponibleException;
use App\Service\Paiement\SynchronisationPaiement;
use App\State\Commande\CommandeDuClient;
use App\State\Commande\VueCommande;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * POST /api/commandes/{numero}/verification-paiement[?jeton=…] → 200 et la commande à jour.
 *
 * Au retour du client depuis la page du prestataire, le statut est relu chez celui-ci
 * (la redirection elle-même ne prouve rien) : utile quand la notification n'est pas encore
 * arrivée, ou ne peut pas arriver (développement local). Prestataire en panne : la commande
 * est renvoyée telle quelle, la page réessaiera.
 *
 * @implements ProcessorInterface<null, CommandeVue>
 */
#[WithMonologChannel('paiement')]
final readonly class VerificationPaiementProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandeDuClient $commandeDuClient,
        private SynchronisationPaiement $synchronisation,
        private VueCommande $vueCommande,
        private LoggerInterface $logger,
        #[Autowire(service: 'limiter.verification_paiement')]
        private RateLimiterFactoryInterface $verificationLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CommandeVue
    {
        $requete = $context['request'] ?? null;
        \assert($requete instanceof Request);
        $commande = $this->commandeDuClient->trouver($uriVariables);

        $limite = $this->verificationLimiter->create($requete->getClientIp())->consume();
        if (!$limite->isAccepted()) {
            throw new TooManyRequestsHttpException($limite->getRetryAfter()->getTimestamp() - time(), 'Trop de vérifications, patientez un instant.');
        }

        try {
            $this->synchronisation->synchroniserCommande($commande);
        } catch (PrestataireIndisponibleException $e) {
            $this->logger->warning('Vérification du paiement de {numero} impossible : {message}', ['numero' => $commande->getNumero(), 'message' => $e->getMessage()]);
        }

        return $this->vueCommande->pour($commande);
    }
}
