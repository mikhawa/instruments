<?php

declare(strict_types=1);

namespace App\State\Commande;

use App\ApiResource\Commande\CommandeVue;
use App\ApiResource\Paiement\PaiementVue;
use App\Entity\Commande;
use App\Service\Paiement\CreationPaiement;
use App\Service\Paiement\Virement\CoordonneesVirement;

/**
 * Construit la vue complète d'une commande : détail, moyens de paiement proposés et dernier paiement.
 */
final readonly class VueCommande
{
    public function __construct(
        private CreationPaiement $creationPaiement,
        private CoordonneesVirement $coordonneesVirement,
    ) {
    }

    public function pour(Commande $commande, bool $avecJeton = false): CommandeVue
    {
        $dernier = $commande->getDernierPaiement();

        return CommandeVue::depuis(
            $commande,
            avecJeton: $avecJeton,
            moyensPaiement: $this->creationPaiement->moyensDisponibles($commande),
            paiement: null !== $dernier ? PaiementVue::depuis($dernier, $this->coordonneesVirement) : null,
        );
    }
}
