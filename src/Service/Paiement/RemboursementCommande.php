<?php

declare(strict_types=1);

namespace App\Service\Paiement;

use App\Entity\Commande;
use App\Entity\Paiement;
use App\Entity\Remboursement;
use App\Entity\Utilisateur;
use App\Enum\PrestatairePaiement;
use App\Enum\StatutCommande;
use App\Service\Commande\NotificationCommande;
use App\Service\Commande\VerrouStock;
use App\Service\Facturation\EmissionFacture;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Remboursement décidé par l'administrateur (prepare/08, tâche 4.3) : total ou partiel,
 * avec ou sans annulation de la commande, toujours documenté par une note de crédit.
 *
 * Dans une transaction, sous verrou de la commande :
 *  - le montant ne dépasse jamais ce qui reste à rembourser (remboursements en cours compris) ;
 *  - annulation demandée : transition « annuler » (le stock réservé est libéré) ;
 *  - le montant est réparti sur les paiements encaissés, du plus récent au plus ancien ;
 *  - commande annulée ou retournée intégralement remboursée : transition « rembourser » ;
 *  - note de crédit émise si la commande a été facturée.
 * Les remboursements sont envoyés au prestataire après la validation (RemboursementPaiement).
 */
#[WithMonologChannel('paiement')]
final readonly class RemboursementCommande
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private VerrouStock $verrouStock,
        private EmissionFacture $emissionFacture,
        private SynchronisationPaiement $synchronisation,
        private NotificationCommande $notification,
        private LoggerInterface $logger,
        #[Target('commande')]
        private WorkflowInterface $workflow,
    ) {
    }

    /**
     * Somme encore remboursable, en centimes.
     */
    public function remboursable(Commande $commande): int
    {
        return array_sum(array_map(fn (Paiement $paiement): int => $this->resteSur($paiement), $commande->getPaiements()->toArray()));
    }

    /**
     * L'annulation n'est proposée qu'avant l'expédition.
     */
    public function peutAnnuler(Commande $commande): bool
    {
        return null !== $commande->getPaidAt() && $this->workflow->can($commande, 'annuler');
    }

    /**
     * @return list<Remboursement>
     *
     * @throws \DomainException demande impossible (message destiné à l'administrateur)
     */
    public function rembourser(Commande $commande, int $montant, string $motif, ?Utilisateur $administrateur, bool $annuler = false): array
    {
        $motif = trim($motif);
        if ('' === $motif) {
            throw new \DomainException('Indiquez le motif du remboursement.');
        }

        $connexion = $this->entityManager->getConnection();
        $connexion->beginTransaction();
        try {
            $this->entityManager->refresh($commande, LockMode::PESSIMISTIC_WRITE);
            if (null === $commande->getPaidAt()) {
                throw new \DomainException('Cette commande n\'a pas été payée.');
            }
            $reste = $this->remboursable($commande);
            if ($montant <= 0 || $montant > $reste) {
                throw new \DomainException(\sprintf('Montant invalide : %s € au plus peuvent être remboursés.', number_format($reste / 100, 2, ',', ' ')));
            }

            if ($annuler) {
                if (!$this->workflow->can($commande, 'annuler')) {
                    throw new \DomainException('Cette commande ne peut plus être annulée (déjà expédiée ?). Remboursez sans annuler, ou enregistrez d\'abord un retour.');
                }
                $this->verrouStock->verrouiller(array_map(static fn ($ligne) => $ligne->getInstrument(), $commande->getLignes()->toArray()));
                $this->workflow->apply($commande, 'annuler');
            }

            $remboursements = $this->repartir($commande, $montant, $motif, $administrateur);

            if ($montant === $reste && \in_array($commande->getStatut(), [StatutCommande::Annulee, StatutCommande::Retournee], true) && $this->workflow->can($commande, 'rembourser')) {
                $this->workflow->apply($commande, 'rembourser');
            }
            $this->emissionFacture->noteDeCredit($commande, $montant, $motif, $remboursements[0]);

            $this->entityManager->flush();
            $connexion->commit();
        } catch (\Throwable $e) {
            $connexion->rollBack();
            throw $e;
        }

        $this->logger->info('Commande {numero} : remboursement de {montant} centimes ({motif}) par {admin}.', [
            'numero' => $commande->getNumero(),
            'montant' => $montant,
            'motif' => $motif,
            'admin' => $administrateur?->getEmail(),
        ]);
        $this->synchronisation->envoyerRemboursements();
        if (!$annuler) {
            // Une annulation prévient déjà le client (EmailCommandeListener)
            try {
                $this->notification->remboursementAccorde($commande, $montant, $motif);
            } catch (\Throwable $e) {
                $this->logger->error('E-mail de remboursement non envoyé : {message}', ['message' => $e->getMessage(), 'numero' => $commande->getNumero()]);
            }
        }

        return $remboursements;
    }

    /**
     * @return list<Remboursement>
     */
    private function repartir(Commande $commande, int $montant, string $motif, ?Utilisateur $administrateur): array
    {
        $remboursements = [];
        foreach (array_reverse($commande->getPaiements()->toArray()) as $paiement) {
            $part = min($montant, $this->resteSur($paiement));
            if ($part <= 0) {
                continue;
            }
            $remboursement = new Remboursement($paiement, $part, $motif, $administrateur);
            $this->entityManager->persist($remboursement);
            $remboursements[] = $remboursement;
            $montant -= $part;
            if (0 === $montant) {
                break;
            }
        }

        return $remboursements;
    }

    /**
     * Encore remboursable sur un paiement : encaissé, moins ce qui est déjà rendu ou en cours de l'être.
     * Un remboursement « échoué » d'un paiement en ligne peut être redemandé ; celui d'un virement
     * (à effectuer à la main) reste dû.
     */
    private function resteSur(Paiement $paiement): int
    {
        if (!$paiement->getStatut()->isEncaisse()) {
            return 0;
        }
        $engage = 0;
        foreach ($paiement->getRemboursements() as $remboursement) {
            $abandonne = Remboursement::STATUT_ECHOUE === $remboursement->getStatut() && PrestatairePaiement::Virement !== $paiement->getPrestataire();
            if (!$abandonne) {
                $engage += $remboursement->getMontant();
            }
        }

        return max(0, $paiement->getMontant() - max($engage, $paiement->getMontantRembourse()));
    }
}
