<?php

declare(strict_types=1);

namespace App\ApiResource\Paiement;

use App\Entity\Paiement;
use App\Enum\PrestatairePaiement;
use App\Service\Paiement\Virement\CoordonneesVirement;

/**
 * Paiement vu par le client : réponse de POST /api/commandes/{numero}/paiements
 * et dernier paiement dans le détail d'une commande.
 */
final readonly class PaiementVue
{
    public function __construct(
        /** Valeur de StatutPaiement */
        public string $statut,
        /** « en_ligne » ou « virement » */
        public string $type,
        public int $montant,
        public ?string $moyen,
        /** Page du prestataire où envoyer le client, tant que le paiement est ouvert */
        public ?string $urlPaiement,
        public ?VirementVue $virement,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function depuis(Paiement $paiement, CoordonneesVirement $coordonnees): self
    {
        $virement = PrestatairePaiement::Virement === $paiement->getPrestataire();

        return new self(
            statut: $paiement->getStatut()->value,
            type: $virement ? 'virement' : 'en_ligne',
            montant: $paiement->getMontant(),
            moyen: $paiement->getMoyen(),
            urlPaiement: !$virement && $paiement->getStatut()->isEnCours() ? $paiement->getUrlPaiement() : null,
            virement: $virement && $paiement->getStatut()->isEnCours() ? new VirementVue(
                beneficiaire: $coordonnees->getBeneficiaire(),
                iban: $coordonnees->getIbanLisible(),
                bic: '' !== $coordonnees->getBic() ? $coordonnees->getBic() : null,
                montant: $paiement->getMontant(),
                communication: (string) $paiement->getReferenceVirement(),
                qrEpc: $coordonnees->contenuQrEpc($paiement),
            ) : null,
            createdAt: $paiement->getCreatedAt(),
        );
    }
}
