<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InstrumentTraductionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contenu traduisible d'un instrument (une ligne par locale).
 */
#[ORM\Entity(repositoryClass: InstrumentTraductionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_instrument_traduction_locale', columns: ['instrument_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_instrument_traduction_slug', columns: ['locale', 'slug'])]
#[UniqueEntity(fields: ['locale', 'slug'], message: 'Ce slug est déjà utilisé dans cette langue.')]
class InstrumentTraduction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'traductions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Instrument $instrument = null;

    #[ORM\Column(length: 5)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\NotBlank]
    #[Assert\Locale]
    private ?string $locale = null;

    #[ORM\Column(length: 150)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 170)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 170)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Le slug ne peut contenir que des minuscules, chiffres et tirets.')]
    private ?string $slug = null;

    #[ORM\Column(length: 300, nullable: true)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\Length(max: 300)]
    private ?string $descriptionCourte = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['instrument:read', 'instrument:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\Length(max: 255)]
    private ?string $materiaux = null;

    /** Contexte culturel et historique de l'instrument */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['instrument:read', 'instrument:write'])]
    private ?string $histoire = null;

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

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescriptionCourte(): ?string
    {
        return $this->descriptionCourte;
    }

    public function setDescriptionCourte(?string $descriptionCourte): static
    {
        $this->descriptionCourte = $descriptionCourte;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMateriaux(): ?string
    {
        return $this->materiaux;
    }

    public function setMateriaux(?string $materiaux): static
    {
        $this->materiaux = $materiaux;

        return $this;
    }

    public function getHistoire(): ?string
    {
        return $this->histoire;
    }

    public function setHistoire(?string $histoire): static
    {
        $this->histoire = $histoire;

        return $this;
    }
}
