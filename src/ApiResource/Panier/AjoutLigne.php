<?php

declare(strict_types=1);

namespace App\ApiResource\Panier;

use App\Entity\Instrument;
use App\Entity\LignePanier;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de POST /api/panier/lignes : {"instrument": "/api/instruments/12", "quantite": 1}.
 * La quantité s'ajoute à celle déjà présente dans le panier.
 */
final class AjoutLigne
{
    #[Assert\NotNull(message: 'Choisissez un instrument.')]
    public ?Instrument $instrument = null;

    #[Assert\Range(min: 1, max: LignePanier::QUANTITE_MAX)]
    public int $quantite = 1;
}
