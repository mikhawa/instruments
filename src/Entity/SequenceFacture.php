<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Dernier numéro attribué par série et par année. Incrémenté sous verrou dans la transaction
 * qui émet la facture (NumerotationFacture) : une transaction annulée ne laisse aucun trou.
 */
#[ORM\Entity]
class SequenceFacture
{
    #[ORM\Id]
    #[ORM\Column(length: 10)]
    private string $serie;

    #[ORM\Id]
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $annee;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $dernierNumero = 0;

    public function __construct(string $serie, int $annee)
    {
        $this->serie = $serie;
        $this->annee = $annee;
    }

    public function getSerie(): string
    {
        return $this->serie;
    }

    public function getAnnee(): int
    {
        return $this->annee;
    }

    public function getDernierNumero(): int
    {
        return $this->dernierNumero;
    }
}
