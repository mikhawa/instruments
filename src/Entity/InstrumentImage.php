<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InstrumentImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Photo d'un instrument ; position 0 = image principale.
 */
#[ORM\Entity(repositoryClass: InstrumentImageRepository::class)]
class InstrumentImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Instrument $instrument = null;

    /** Chemin relatif sous public/uploads/instruments/ */
    #[ORM\Column(length: 255)]
    #[Groups(['instrument:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $fichier = null;

    /** Texte alternatif (accessibilité) */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['instrument:read'])]
    #[Assert\Length(max: 255)]
    private ?string $alt = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    #[Groups(['instrument:read'])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstrument(): ?Instrument
    {
        return $this->instrument;
    }

    public function setInstrument(?Instrument $instrument): static
    {
        $this->instrument = $instrument;

        return $this;
    }

    public function getFichier(): ?string
    {
        return $this->fichier;
    }

    public function setFichier(string $fichier): static
    {
        $this->fichier = $fichier;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
