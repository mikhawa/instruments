<?php

declare(strict_types=1);

namespace App\ApiResource\Commande;

/**
 * Ligne de commande telle qu'elle a été figée à l'achat.
 */
final readonly class LigneCommandeVue
{
    public function __construct(
        public string $reference,
        public string $libelle,
        public int $quantite,
        public int $prixUnitaireTtc,
        public int $totalTtc,
        /** Pour le lien vers la fiche, si l'instrument existe encore */
        public ?int $instrumentId,
    ) {
    }
}
