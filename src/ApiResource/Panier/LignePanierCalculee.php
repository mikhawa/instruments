<?php

declare(strict_types=1);

namespace App\ApiResource\Panier;

/**
 * Ligne du panier recalculée. Une ligne indisponible reste affichée
 * mais n'entre pas dans les totaux.
 */
final readonly class LignePanierCalculee
{
    public const ALERTE_INDISPONIBLE = 'indisponible';
    public const ALERTE_QUANTITE_REDUITE = 'quantite_reduite';
    public const ALERTE_PRIX_MODIFIE = 'prix_modifie';

    /**
     * @param list<string> $alertes
     */
    public function __construct(
        /** Identifiant à utiliser pour PATCH / DELETE /api/panier/lignes/{id} */
        public int $id,
        public InstrumentResume $instrument,
        /** Quantité retenue, ramenée au maximum disponible si besoin */
        public int $quantite,
        /** 0 si l'instrument n'est plus disponible */
        public int $quantiteMax,
        public int $prixUnitaireTtc,
        /** Prix TTC lors de l'ajout, seulement si le prix a changé depuis */
        public ?int $prixUnitaireTtcAjout,
        public int $totalTtc,
        public bool $disponible,
        public array $alertes,
    ) {
    }
}
