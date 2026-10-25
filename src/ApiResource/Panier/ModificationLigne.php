<?php

declare(strict_types=1);

namespace App\ApiResource\Panier;

use App\Entity\LignePanier;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de PATCH /api/panier/lignes/{id} : {"quantite": 2} (nouvelle quantité, pas un ajout).
 */
final class ModificationLigne
{
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: LignePanier::QUANTITE_MAX)]
    public ?int $quantite = null;
}
