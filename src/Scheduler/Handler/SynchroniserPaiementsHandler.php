<?php

declare(strict_types=1);

namespace App\Scheduler\Handler;

use App\Entity\Paiement;
use App\Repository\PaiementRepository;
use App\Scheduler\Message\SynchroniserPaiements;
use App\Service\Paiement\SynchronisationPaiement;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Filet de sécurité (prepare/03-paiement.md § 5) : un paiement en ligne dont la notification
 * s'est perdue est relu chez le prestataire. Seuls les paiements de plus de 2 minutes
 * (la notification a eu le temps d'arriver) et de moins de 3 jours sont concernés.
 * Un échec sur un paiement n'empêche pas les autres.
 */
#[AsMessageHandler]
#[WithMonologChannel('paiement')]
final readonly class SynchroniserPaiementsHandler
{
    public function __construct(
        private PaiementRepository $paiementRepository,
        private SynchronisationPaiement $synchronisation,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SynchroniserPaiements $message): int
    {
        $this->entityManager->clear();
        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $traites = 0;

        foreach ($this->paiementRepository->idsEnLigneASynchroniser($maintenant->modify('-3 days'), $maintenant->modify('-2 minutes')) as $id) {
            try {
                $paiement = $this->entityManager->find(Paiement::class, $id);
                if (null !== $paiement) {
                    $this->synchronisation->synchroniser($paiement);
                    ++$traites;
                }
            } catch (\Throwable $e) {
                $this->entityManager->clear();
                $this->logger->error('Synchronisation du paiement {id} impossible : {message}', ['id' => $id, 'message' => $e->getMessage()]);
            }
        }

        $this->synchronisation->envoyerRemboursements();

        return $traites;
    }
}
