<?php

declare(strict_types=1);

namespace App\Service\Paiement;

use App\Entity\Commande;
use App\Entity\Paiement;
use App\Enum\PrestatairePaiement;
use App\Repository\PaiementRepository;
use App\Repository\RemboursementRepository;

/**
 * Relit le statut d'un paiement en ligne chez son prestataire et l'applique (EncaissementPaiement),
 * puis envoie les remboursements que cela a décidés. Point d'entrée commun de la notification
 * du prestataire, du retour du client sur le site et de la synchronisation planifiée.
 */
final readonly class SynchronisationPaiement
{
    public function __construct(
        private Prestataires $prestataires,
        private EncaissementPaiement $encaissement,
        private RemboursementPaiement $remboursement,
        private PaiementRepository $paiementRepository,
        private RemboursementRepository $remboursementRepository,
    ) {
    }

    /**
     * @return string|null résultat d'EncaissementPaiement, NULL pour un virement (validé à la main)
     *
     * @throws PrestataireIndisponibleException
     */
    public function synchroniser(Paiement $paiement): ?string
    {
        if (PrestatairePaiement::Virement === $paiement->getPrestataire() || null === $paiement->getIdExterne()) {
            return null;
        }

        // Appel au prestataire hors transaction : aucun verrou n'est tenu pendant l'attente réseau
        $etat = $this->prestataires->de($paiement)->etat($paiement);
        $resultat = $this->encaissement->appliquer($paiement, $etat);
        $this->envoyerRemboursements();

        return $resultat;
    }

    /**
     * Paiements en ligne encore en cours d'une commande (retour du client depuis la page de paiement).
     */
    public function synchroniserCommande(Commande $commande): void
    {
        foreach ($this->paiementRepository->enLigneEnCoursDe($commande) as $paiement) {
            $this->synchroniser($paiement);
        }
    }

    public function envoyerRemboursements(): void
    {
        foreach ($this->remboursementRepository->idsAEffectuer() as $id) {
            $this->remboursement->effectuer($id);
        }
    }
}
