<?php

declare(strict_types=1);

namespace App\EventListener\Workflow;

use App\Entity\Commande;
use App\Service\Commande\NotificationCommande;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * E-mails liés aux transitions. Une commande remplacée par une nouvelle commande
 * du même panier (contexte « remplacee ») est annulée sans prévenir le client ;
 * l'expiration d'une commande non payée n'envoie rien non plus.
 */
final readonly class EmailCommandeListener
{
    public const CONTEXTE_REMPLACEE = 'remplacee';

    public function __construct(private NotificationCommande $notification)
    {
    }

    #[AsEventListener(event: 'workflow.commande.completed.expedier')]
    public function expedition(CompletedEvent $event): void
    {
        $this->notification->commandeExpediee($this->commande($event));
    }

    #[AsEventListener(event: 'workflow.commande.completed.annuler')]
    public function annulation(CompletedEvent $event): void
    {
        if (!($event->getContext()[self::CONTEXTE_REMPLACEE] ?? false)) {
            $this->notification->commandeAnnulee($this->commande($event));
        }
    }

    private function commande(CompletedEvent $event): Commande
    {
        $commande = $event->getSubject();
        \assert($commande instanceof Commande);

        return $commande;
    }
}
