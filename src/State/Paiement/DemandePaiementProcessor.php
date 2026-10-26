<?php

declare(strict_types=1);

namespace App\State\Paiement;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Paiement\DemandePaiement;
use App\ApiResource\Paiement\PaiementVue;
use App\Service\Paiement\CreationPaiement;
use App\Service\Paiement\PaiementRefuseException;
use App\Service\Paiement\PrestataireIndisponibleException;
use App\Service\Paiement\Virement\CoordonneesVirement;
use App\State\Commande\CommandeDuClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * POST /api/commandes/{numero}/paiements[?jeton=…] {"moyen": "en_ligne" | "virement"} → 201.
 * En ligne : le client est ensuite envoyé sur urlPaiement. Virement : informations à recopier.
 * 404 commande inaccessible, 409 commande plus payable ou moyen non proposé, 503 prestataire en panne.
 *
 * @implements ProcessorInterface<DemandePaiement, PaiementVue>
 */
final readonly class DemandePaiementProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandeDuClient $commandeDuClient,
        private CreationPaiement $creationPaiement,
        private CoordonneesVirement $coordonneesVirement,
        #[Autowire(service: 'limiter.demande_paiement')]
        private RateLimiterFactoryInterface $demandePaiementLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PaiementVue
    {
        \assert($data instanceof DemandePaiement);
        $requete = $context['request'] ?? null;
        \assert($requete instanceof Request);

        $commande = $this->commandeDuClient->trouver($uriVariables);

        $limite = $this->demandePaiementLimiter->create($requete->getClientIp())->consume();
        if (!$limite->isAccepted()) {
            throw new TooManyRequestsHttpException($limite->getRetryAfter()->getTimestamp() - time(), 'Trop de tentatives de paiement, réessayez dans quelques minutes.');
        }

        try {
            $paiement = DemandePaiement::VIREMENT === $data->moyen
                ? $this->creationPaiement->parVirement($commande)
                : $this->creationPaiement->enLigne($commande);
        } catch (PaiementRefuseException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (PrestataireIndisponibleException $e) {
            // Détail technique dans les journaux seulement
            throw new ServiceUnavailableHttpException(60, 'Le service de paiement ne répond pas. Réessayez dans un instant.', $e);
        }

        return PaiementVue::depuis($paiement, $this->coordonneesVirement);
    }
}
