<?php

declare(strict_types=1);

namespace App\EventListener\Workflow;

use App\Entity\Commande;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Dates des étapes de la commande, posées au passage de chaque transition.
 */
final class HorodatageCommandeListener
{
    #[AsEventListener(event: 'workflow.commande.completed')]
    public function __invoke(CompletedEvent $event): void
    {
        $commande = $event->getSubject();
        if (!$commande instanceof Commande) {
            return;
        }
        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        match ($event->getTransition()?->getName()) {
            'payer' => $commande->setPaidAt($commande->getPaidAt() ?? $maintenant)->setExpireAt(null),
            'expedier' => $commande->setShippedAt($maintenant),
            'livrer' => $commande->setDeliveredAt($maintenant),
            'annuler', 'expirer' => $commande->setCancelledAt($maintenant)->setExpireAt(null),
            default => null,
        };
    }
}
