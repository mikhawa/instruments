<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\EvenementWebhook;
use App\Message\TraiterNotificationPaiement;
use App\Repository\PaiementRepository;
use App\Service\Paiement\PrestataireIndisponibleException;
use App\Service\Paiement\SynchronisationPaiement;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Relit chez le prestataire le paiement signalé par une notification et applique son statut.
 * Une panne du prestataire relance l'exception : Messenger réessaie (3 fois, délai croissant),
 * puis la synchronisation planifiée prend le relais.
 */
#[AsMessageHandler]
#[WithMonologChannel('paiement')]
final readonly class TraiterNotificationPaiementHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaiementRepository $paiementRepository,
        private SynchronisationPaiement $synchronisation,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TraiterNotificationPaiement $message): void
    {
        // Le worker vit longtemps : on repart d'un gestionnaire d'entités vide (données à jour)
        $this->entityManager->clear();
        $evenement = $this->entityManager->find(EvenementWebhook::class, $message->evenementId);
        if (null === $evenement || null !== $evenement->getTraiteAt()) {
            return;
        }

        $paiement = $this->paiementRepository->findOneBy(['prestataire' => $evenement->getPrestataire(), 'idExterne' => $evenement->getReference()]);
        if (null === $paiement) {
            $evenement->traite('Paiement inconnu.');
            $this->entityManager->flush();
            $this->logger->warning('Notification pour un paiement inconnu : {reference}.', ['reference' => $evenement->getReference()]);

            return;
        }

        try {
            $this->synchronisation->synchroniser($paiement);
        } catch (PrestataireIndisponibleException $e) {
            $this->entityManager->clear();
            // Erreur notée, sans marquer l'événement traité : le nouvel essai de Messenger le reprendra
            $this->entityManager->find(EvenementWebhook::class, $message->evenementId)?->echec($e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }

        $evenement = $this->entityManager->find(EvenementWebhook::class, $message->evenementId);
        $evenement?->traite();
        $this->entityManager->flush();
    }
}
