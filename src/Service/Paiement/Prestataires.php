<?php

declare(strict_types=1);

namespace App\Service\Paiement;

use App\Entity\Paiement;
use App\Enum\PrestatairePaiement;
use App\Service\Paiement\Mollie\MolliePrestataire;
use App\Service\Paiement\Simulation\SimulationPrestataire;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Choix du prestataire de paiement en ligne :
 *  - Mollie dès que MOLLIE_API_KEY est renseignée (clé test_… en préprod, live_… en prod) ;
 *  - sinon la simulation, hors production uniquement ;
 *  - sinon aucun : le paiement en ligne est indisponible (prod mal configurée).
 */
final readonly class Prestataires
{
    public function __construct(
        private MolliePrestataire $mollie,
        private SimulationPrestataire $simulation,
        #[Autowire(param: 'kernel.environment')]
        private string $environnement,
    ) {
    }

    public function enLigne(): ?PrestatairePaiementInterface
    {
        if ($this->mollie->isConfigure()) {
            return $this->mollie;
        }

        return $this->simulationAutorisee() ? $this->simulation : null;
    }

    /**
     * Prestataire qui a créé ce paiement (le statut se relit toujours chez lui).
     */
    public function de(Paiement $paiement): PrestatairePaiementInterface
    {
        return match ($paiement->getPrestataire()) {
            PrestatairePaiement::Mollie => $this->mollie,
            PrestatairePaiement::Simulation => $this->simulationAutorisee() ? $this->simulation : throw new PrestataireIndisponibleException('Paiement simulé refusé en production.'),
            PrestatairePaiement::Virement => throw new \LogicException('Un virement n\'a pas de prestataire en ligne.'),
        };
    }

    public function simulationAutorisee(): bool
    {
        return 'prod' !== $this->environnement;
    }
}
