<?php

declare(strict_types=1);

namespace App\EventListener\Workflow;

use App\Entity\Commande;
use App\Enum\MotifAnnulation;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Dates des étapes de la commande, posées au passage de chaque transition,
 * et motif d'une annulation.
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
            'payer_apres_expiration' => $commande->setPaidAt($maintenant)->setCancelledAt(null)->setMotifAnnulation(null),
            'expedier' => $commande->setShippedAt($maintenant),
            'livrer' => $commande->setDeliveredAt($maintenant),
            'expirer' => $commande->setCancelledAt($maintenant)->setExpireAt(null)->setMotifAnnulation(MotifAnnulation::Expiree),
            'annuler' => $commande->setCancelledAt($maintenant)->setExpireAt(null)->setMotifAnnulation(
                ($event->getContext()[EmailCommandeListener::CONTEXTE_REMPLACEE] ?? false) ? MotifAnnulation::Remplacee : MotifAnnulation::Annulee,
            ),
            default => null,
        };
    }
}
