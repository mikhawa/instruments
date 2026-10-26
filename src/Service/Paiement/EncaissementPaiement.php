<?php

declare(strict_types=1);

namespace App\Service\Paiement;

use App\Entity\Commande;
use App\Entity\Paiement;
use App\Entity\Remboursement;
use App\Enum\PrestatairePaiement;
use App\Enum\StatutCommande;
use App\Enum\StatutPaiement;
use App\Service\Commande\NotificationCommande;
use App\Service\Commande\VerrouStock;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Applique à un paiement le statut lu chez le prestataire (ou la réception d'un virement)
 * et en tire les conséquences sur la commande, une seule fois (prepare/03-paiement.md § 5).
 *
 * Dans une transaction, sous verrou de la commande puis du paiement (même ordre que CreationPaiement) :
 *  - le statut, le moyen et les montants remboursés du paiement sont mis à jour ;
 *  - s'il vient d'être encaissé, son montant et sa devise sont comparés à la commande (sinon : anomalie,
 *    rien n'est appliqué) ;
 *  - commande en attente → « payer » ;
 *  - commande expirée sans paiement → reprise (« payer_apres_expiration ») si le stock suffit encore,
 *    sinon remboursement intégral ; une commande annulée pour un autre motif est remboursée ;
 *  - commande déjà payée → paiement en double, remboursé.
 *
 * Un même statut appliqué deux fois (notification rejouée, synchronisation planifiée) ne fait rien de plus.
 * Les remboursements sont seulement enregistrés ici ; RemboursementPaiement les envoie après la validation.
 */
#[WithMonologChannel('paiement')]
final readonly class EncaissementPaiement
{
    public const AUCUN = 'aucun';
    public const PAYEE = 'payee';
    public const REPRISE = 'reprise';
    public const REMBOURSE = 'rembourse';
    public const DOUBLON = 'doublon';
    public const ANOMALIE = 'anomalie';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private VerrouStock $verrouStock,
        private NotificationCommande $notification,
        private LoggerInterface $logger,
        #[Target('commande')]
        private WorkflowInterface $workflow,
    ) {
    }

    /**
     * @return self::* ce qui a été fait de la commande
     */
    public function appliquer(Paiement $paiement, EtatPaiement $etat): string
    {
        $commande = $paiement->getCommande();
        $connexion = $this->entityManager->getConnection();
        $connexion->beginTransaction();
        try {
            // Relecture sous verrou : un autre processus (notification, synchronisation, expiration) attend son tour
            $this->entityManager->refresh($commande, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($paiement, LockMode::PESSIMISTIC_WRITE);

            $dejaEncaisse = $paiement->getStatut()->isEncaisse();
            $paiement->setMoyen($etat->moyen ?? $paiement->getMoyen())->ajouterDonnees($etat->details);
            if ($etat->montantRembourse > $paiement->getMontantRembourse()) {
                $paiement->setMontantRembourse($etat->montantRembourse);
            }
            // Un paiement encaissé ne redevient jamais « ouvert » ou « échoué » (réponse incohérente ou périmée)
            $paiement->setStatut($dejaEncaisse && !$etat->statut->isEncaisse() ? $paiement->getStatut() : $etat->statut);

            $resultat = self::AUCUN;
            if (!$dejaEncaisse && $etat->statut->isEncaisse()) {
                $resultat = $this->montantConforme($paiement, $etat) ? $this->encaisser($commande, $paiement) : self::ANOMALIE;
            }

            $this->entityManager->flush();
            $connexion->commit();
        } catch (\Throwable $e) {
            $connexion->rollBack();
            throw $e;
        }

        $this->logger->info('Paiement {paiement} de la commande {numero} : {statut}, {resultat}.', [
            'paiement' => $paiement->getId(),
            'numero' => $commande->getNumero(),
            'statut' => $paiement->getStatut()->value,
            'resultat' => $resultat,
        ]);
        $this->notifier($resultat, $commande, $paiement);

        return $resultat;
    }

    /**
     * Virement constaté sur le compte bancaire par l'administrateur (montant et communication vérifiés).
     *
     * @return self::*
     */
    public function confirmerVirement(Paiement $paiement): string
    {
        if (PrestatairePaiement::Virement !== $paiement->getPrestataire()) {
            throw new \LogicException('Seul un virement se confirme à la main.');
        }

        return $this->appliquer($paiement, new EtatPaiement(StatutPaiement::Paye, $paiement->getMontant(), $paiement->getDevise(), 'virement'));
    }

    private function montantConforme(Paiement $paiement, EtatPaiement $etat): bool
    {
        $commande = $paiement->getCommande();
        if ($etat->montant === $paiement->getMontant() && $etat->devise === $paiement->getDevise() && $paiement->getMontant() === $commande->getTotalTtc()) {
            return true;
        }

        $anomalie = \sprintf('Montant encaissé %d %s, attendu %d %s (commande : %d).', $etat->montant, $etat->devise, $paiement->getMontant(), $paiement->getDevise(), $commande->getTotalTtc());
        $paiement->ajouterDonnees(['anomalie' => $anomalie]);
        $this->logger->critical('Paiement {paiement} de la commande {numero} non appliqué : {anomalie}', [
            'paiement' => $paiement->getId(),
            'numero' => $commande->getNumero(),
            'anomalie' => $anomalie,
        ]);

        return false;
    }

    /**
     * @return self::*
     */
    private function encaisser(Commande $commande, Paiement $paiement): string
    {
        if (null !== $commande->getPaidAt()) {
            $this->rembourserIntegralement($paiement, \sprintf('Paiement en double de la commande %s', $commande->getNumero()));
            $this->logger->warning('Commande {numero} déjà payée : le paiement {paiement} est remboursé.', ['numero' => $commande->getNumero(), 'paiement' => $paiement->getId()]);

            return self::DOUBLON;
        }

        if (StatutCommande::EnAttentePaiement === $commande->getStatut()) {
            $this->workflow->apply($commande, 'payer', ['paiement' => $paiement->getId()]);
            $this->apresPaiement($commande, $paiement);

            return self::PAYEE;
        }

        if ($this->workflow->can($commande, 'payer_apres_expiration') && $this->stockSuffisant($commande)) {
            // StockCommandeListener réserve de nouveau les quantités (stocks verrouillés ci-dessus)
            $this->workflow->apply($commande, 'payer_apres_expiration', ['paiement' => $paiement->getId()]);
            $this->apresPaiement($commande, $paiement);

            return self::REPRISE;
        }

        // Commande annulée (ou expirée sans stock suffisant) : l'argent reçu est rendu
        $commande->setPaidAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        if ($this->workflow->can($commande, 'rembourser')) {
            $this->workflow->apply($commande, 'rembourser', ['paiement' => $paiement->getId()]);
        }
        $this->rembourserIntegralement($paiement, \sprintf('Commande %s annulée avant la réception du paiement', $commande->getNumero()));
        $this->logger->warning('Paiement {paiement} reçu pour la commande {numero} déjà annulée ({motif}) : remboursement intégral.', [
            'paiement' => $paiement->getId(),
            'numero' => $commande->getNumero(),
            'motif' => $commande->getMotifAnnulation()?->value,
        ]);

        return self::REMBOURSE;
    }

    /**
     * Toutes les lignes sont-elles encore disponibles ? Les stocks restent verrouillés jusqu'à la fin de la transaction.
     */
    private function stockSuffisant(Commande $commande): bool
    {
        $lignes = $commande->getLignes()->toArray();
        $this->verrouStock->verrouiller(array_map(static fn ($ligne) => $ligne->getInstrument(), $lignes));

        foreach ($lignes as $ligne) {
            $stock = $ligne->getInstrument()?->getStock();
            if (null === $stock || $stock->getQuantiteDisponible() < $ligne->getQuantite()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Commande payée : un virement encore attendu n'a plus lieu d'être, et les instruments
     * achetés quittent le panier d'origine (le client a pu y ajouter autre chose depuis).
     */
    private function apresPaiement(Commande $commande, Paiement $paiement): void
    {
        foreach ($commande->getPaiements() as $autre) {
            if ($autre !== $paiement && PrestatairePaiement::Virement === $autre->getPrestataire() && $autre->getStatut()->isEnCours()) {
                $autre->setStatut(StatutPaiement::Annule);
            }
        }

        $panier = $commande->getPanier();
        if (null === $panier) {
            return;
        }
        foreach ($commande->getLignes() as $ligneCommande) {
            $instrument = $ligneCommande->getInstrument();
            $lignePanier = null !== $instrument ? $panier->getLignePour($instrument) : null;
            if (null !== $lignePanier) {
                $panier->retirerLigne($lignePanier);
            }
        }
    }

    private function rembourserIntegralement(Paiement $paiement, string $motif): void
    {
        $reste = $paiement->getMontant() - $paiement->getMontantRembourse();
        if ($reste > 0) {
            $this->entityManager->persist(new Remboursement($paiement, $reste, $motif));
        }
    }

    private function notifier(string $resultat, Commande $commande, Paiement $paiement): void
    {
        try {
            match ($resultat) {
                self::PAYEE, self::REPRISE => $this->notification->commandePayee($commande),
                self::REMBOURSE => $this->notification->paiementRembourse($commande, $paiement->getMontant()),
                default => null,
            };
        } catch (\Throwable $e) {
            // Le paiement est enregistré : un e-mail en échec ne doit pas faire rejouer le traitement
            $this->logger->error('E-mail de paiement non envoyé : {message}', ['message' => $e->getMessage(), 'numero' => $commande->getNumero()]);
        }
    }
}
