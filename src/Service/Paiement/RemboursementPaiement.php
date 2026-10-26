<?php

declare(strict_types=1);

namespace App\Service\Paiement;

use App\Entity\Remboursement;
use App\Enum\PrestatairePaiement;
use App\Enum\StatutPaiement;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

/**
 * Envoie au prestataire un remboursement enregistré « à effectuer ».
 *
 * Le remboursement est verrouillé et passé « en cours » avant l'appel : deux processus ne peuvent
 * pas l'envoyer deux fois. En cas de panne passagère, il redevient « à effectuer » et la
 * synchronisation planifiée le relance ; un refus définitif le passe « échoué » (à traiter à la main).
 * Un virement se rembourse à la main : le remboursement reste « en cours » avec une note.
 */
#[WithMonologChannel('paiement')]
final readonly class RemboursementPaiement
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Prestataires $prestataires,
        private LoggerInterface $logger,
    ) {
    }

    public function effectuer(int $id): void
    {
        $remboursement = $this->reserver($id);
        if (null === $remboursement) {
            return;
        }
        $paiement = $remboursement->getPaiement();

        if (PrestatairePaiement::Virement === $paiement->getPrestataire()) {
            $remboursement->echec('À rembourser à la main par virement (IBAN du client).');
            $this->entityManager->flush();
            $this->logger->warning('Remboursement {id} du virement {paiement} à effectuer à la main.', ['id' => $id, 'paiement' => $paiement->getId()]);

            return;
        }

        try {
            [$idExterne, $effectue] = $this->prestataires->de($paiement)->rembourser($remboursement);
        } catch (PrestataireIndisponibleException $e) {
            $this->entityManager->refresh($remboursement);
            if ($e->definitif) {
                $remboursement->echec($e->getMessage());
            } else {
                $remboursement->aRelancer($e->getMessage());
            }
            $this->entityManager->flush();
            $this->logger->error('Remboursement {id} non envoyé : {message}', ['id' => $id, 'message' => $e->getMessage()]);

            return;
        }

        $remboursement->envoye($idExterne, $effectue);
        $paiement->setMontantRembourse($paiement->getMontantRembourse() + $remboursement->getMontant());
        $paiement->setStatut($paiement->getMontantRembourse() >= $paiement->getMontant() ? StatutPaiement::Rembourse : StatutPaiement::PartiellementRembourse);
        $this->entityManager->flush();
        $this->logger->info('Remboursement {id} de {montant} centimes envoyé ({externe}).', ['id' => $id, 'montant' => $remboursement->getMontant(), 'externe' => $idExterne]);
    }

    /**
     * Passe le remboursement « en cours » s'il est encore « à effectuer » (sinon NULL : déjà pris en charge).
     */
    private function reserver(int $id): ?Remboursement
    {
        $connexion = $this->entityManager->getConnection();
        $connexion->beginTransaction();
        try {
            $remboursement = $this->entityManager->find(Remboursement::class, $id);
            if (null !== $remboursement) {
                $this->entityManager->refresh($remboursement, LockMode::PESSIMISTIC_WRITE);
            }
            if (null === $remboursement || Remboursement::STATUT_A_EFFECTUER !== $remboursement->getStatut()) {
                $connexion->commit();

                return null;
            }
            $remboursement->prendreEnCharge();
            $this->entityManager->flush();
            $connexion->commit();
        } catch (\Throwable $e) {
            $connexion->rollBack();
            throw $e;
        }

        return $remboursement;
    }
}
