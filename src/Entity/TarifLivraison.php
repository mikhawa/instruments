<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ClasseLivraison;
use App\Repository\TarifLivraisonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Case de la grille : prix TTC d'un envoi pour une zone, un mode et une classe de colis.
 * Absence de tarif = combinaison non proposée.
 */
#[ORM\Entity(repositoryClass: TarifLivraisonRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_tarif_livraison', columns: ['zone_id', 'mode_id', 'classe'])]
#[UniqueEntity(fields: ['zone', 'mode', 'classe'], message: 'Un tarif existe déjà pour cette zone, ce mode et cette classe.')]
class TarifLivraison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?ZoneLivraison $zone = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?ModeLivraison $mode = null;

    #[ORM\Column(length: 20, enumType: ClasseLivraison::class)]
    #[Assert\NotNull]
    #[Assert\Choice(callback: [ClasseLivraison::class, 'expediables'], message: 'Seules les classes expédiables ont un tarif.')]
    private ?ClasseLivraison $classe = null;

    /** Centimes TTC, pour l'envoi entier */
    #[ORM\Column(options: ['unsigned' => true])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private ?int $prixTtc = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    #[Assert\PositiveOrZero]
    private ?int $delaiJoursMin = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    #[Assert\PositiveOrZero]
    #[Assert\Expression('value === null or this.getDelaiJoursMin() === null or value >= this.getDelaiJoursMin()', message: 'Le délai maximal doit être supérieur ou égal au délai minimal.')]
    private ?int $delaiJoursMax = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getZone(): ?ZoneLivraison
    {
        return $this->zone;
    }

    public function setZone(?ZoneLivraison $zone): static
    {
        $this->zone = $zone;

        return $this;
    }

    public function getMode(): ?ModeLivraison
    {
        return $this->mode;
    }

    public function setMode(?ModeLivraison $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getClasse(): ?ClasseLivraison
    {
        return $this->classe;
    }

    public function setClasse(?ClasseLivraison $classe): static
    {
        $this->classe = $classe;

        return $this;
    }

    public function getPrixTtc(): ?int
    {
        return $this->prixTtc;
    }

    public function setPrixTtc(?int $prixTtc): static
    {
        $this->prixTtc = $prixTtc;

        return $this;
    }

    public function getDelaiJoursMin(): ?int
    {
        return $this->delaiJoursMin;
    }

    public function setDelaiJoursMin(?int $delaiJoursMin): static
    {
        $this->delaiJoursMin = $delaiJoursMin;

        return $this;
    }

    public function getDelaiJoursMax(): ?int
    {
        return $this->delaiJoursMax;
    }

    public function setDelaiJoursMax(?int $delaiJoursMax): static
    {
        $this->delaiJoursMax = $delaiJoursMax;

        return $this;
    }
}
