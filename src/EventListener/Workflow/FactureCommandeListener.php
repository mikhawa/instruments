<?php

declare(strict_types=1);

namespace App\EventListener\Workflow;

use App\Entity\Commande;
use App\Service\Facturation\EmissionFacture;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Facture émise au paiement de la commande, dans la transaction qui enregistre le paiement
 * (EncaissementPaiement) : la numérotation reste continue même si celle-ci est annulée.
 * L'appelant enregistre (flush).
 */
final readonly class FactureCommandeListener
{
    public function __construct(private EmissionFacture $emission)
    {
    }

    #[AsEventListener(event: 'workflow.commande.completed.payer')]
    #[AsEventListener(event: 'workflow.commande.completed.payer_apres_expiration')]
    public function __invoke(CompletedEvent $event): void
    {
        $commande = $event->getSubject();
        \assert($commande instanceof Commande);

        $this->emission->facturer($commande);
    }
}
