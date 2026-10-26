<?php

declare(strict_types=1);

namespace App\EventListener\Workflow;

use App\Entity\Commande;
use App\Entity\MouvementStock;
use App\Enum\TypeMouvementStock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Effets des transitions de commande sur le stock, chacun tracé dans mouvement_stock.
 * La réservation initiale est faite à la création (CreationCommande).
 *
 *  - annuler / expirer : la quantité réservée redevient disponible ;
 *  - expedier : la quantité réservée sort du stock ;
 *  - payer_apres_expiration : la quantité est de nouveau réservée (l'appelant a vérifié la disponibilité).
 *
 * L'appelant enregistre (flush) et, pour éviter des données périmées,
 * verrouille les stocks concernés au préalable (VerrouStock).
 */
final readonly class StockCommandeListener
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[AsEventListener(event: 'workflow.commande.completed.annuler')]
    #[AsEventListener(event: 'workflow.commande.completed.expirer')]
    public function liberer(CompletedEvent $event): void
    {
        $commande = $this->commande($event);
        $commentaire = 'expirer' === $event->getTransition()?->getName() ? 'Commande expirée' : 'Commande annulée';

        foreach ($commande->getLignes() as $ligne) {
            $instrument = $ligne->getInstrument();
            if (null === $instrument) {
                continue;
            }
            $instrument->getStock()?->liberer($ligne->getQuantite());
            $this->entityManager->persist(new MouvementStock($instrument, TypeMouvementStock::Liberation, $ligne->getQuantite(), $commande, commentaire: $commentaire));
        }
    }

    #[AsEventListener(event: 'workflow.commande.completed.payer_apres_expiration')]
    public function reserverDeNouveau(CompletedEvent $event): void
    {
        $commande = $this->commande($event);

        foreach ($commande->getLignes() as $ligne) {
            $instrument = $ligne->getInstrument();
            if (null === $instrument) {
                continue;
            }
            // Lève une DomainException si le stock ne suffit plus : la transaction de l'appelant est annulée
            $instrument->getStock()?->reserver($ligne->getQuantite());
            $this->entityManager->persist(new MouvementStock($instrument, TypeMouvementStock::Reservation, -$ligne->getQuantite(), $commande, commentaire: 'Paiement reçu après expiration'));
        }
    }

    #[AsEventListener(event: 'workflow.commande.completed.expedier')]
    public function sortir(CompletedEvent $event): void
    {
        $commande = $this->commande($event);

        foreach ($commande->getLignes() as $ligne) {
            $instrument = $ligne->getInstrument();
            if (null === $instrument) {
                continue;
            }
            $instrument->getStock()?->sortir($ligne->getQuantite());
            $this->entityManager->persist(new MouvementStock($instrument, TypeMouvementStock::Sortie, -$ligne->getQuantite(), $commande));
        }
    }

    private function commande(CompletedEvent $event): Commande
    {
        $commande = $event->getSubject();
        \assert($commande instanceof Commande);

        return $commande;
    }
}
