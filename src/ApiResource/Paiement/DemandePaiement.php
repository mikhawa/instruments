<?php

declare(strict_types=1);

namespace App\ApiResource\Paiement;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de POST /api/commandes/{numero}/paiements. Le montant n'est jamais envoyé par le client.
 */
final class DemandePaiement
{
    public const EN_LIGNE = 'en_ligne';
    public const VIREMENT = 'virement';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::EN_LIGNE, self::VIREMENT], message: 'Moyen de paiement inconnu.')]
    public string $moyen = '';
}
