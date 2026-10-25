<?php

declare(strict_types=1);

namespace App\Scheduler\Handler;

use App\Entity\Commande;
use App\Enum\StatutCommande;
use App\Repository\CommandeRepository;
use App\Scheduler\Message\ExpirerCommandes;
use App\Service\Commande\VerrouStock;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Applique la transition « expirer » (stock libéré par StockCommandeListener) à chaque commande
 * non payée dont expire_at est dépassé. Une transaction par commande : un échec n'empêche pas les autres.
 */
#[AsMessageHandler]
final readonly class ExpirerCommandesHandler
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private VerrouStock $verrouStock,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        #[Target('commande')]
        private WorkflowInterface $workflow,
    ) {
    }

    public function __invoke(ExpirerCommandes $message): int
    {
        // Le worker vit longtemps : on repart d'un gestionnaire d'entités vide (données à jour)
        $this->entityManager->clear();
        $expirees = 0;

        foreach ($this->commandeRepository->idsExpirees(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) as $id) {
            $connexion = $this->entityManager->getConnection();
            $connexion->beginTransaction();
            try {
                // Verrou sur la commande : un paiement confirmé au même instant ne peut pas se croiser avec l'expiration
                $commande = $this->entityManager->find(Commande::class, $id, LockMode::PESSIMISTIC_WRITE);
                if (null === $commande || StatutCommande::EnAttentePaiement !== $commande->getStatut()) {
                    $connexion->commit();
                    continue;
                }
                $this->verrouStock->verrouiller(array_map(static fn ($ligne) => $ligne->getInstrument(), $commande->getLignes()->toArray()));
                $this->workflow->apply($commande, 'expirer');
                $this->entityManager->flush();
                $connexion->commit();
                ++$expirees;
            } catch (\Throwable $e) {
                $connexion->rollBack();
                $this->entityManager->clear();
                $this->logger->error('Expiration de la commande {id} impossible : {message}', ['id' => $id, 'message' => $e->getMessage()]);
            }
        }

        if ($expirees > 0) {
            $this->logger->info('{nombre} commande(s) expirée(s), stock libéré.', ['nombre' => $expirees]);
        }

        return $expirees;
    }
}
