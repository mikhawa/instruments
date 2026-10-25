<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ModeLivraisonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mode de livraison proposé au client (ex. « À domicile, avec suivi », « Retrait à l'atelier »).
 * Un mode « retrait » est gratuit, sans adresse de livraison ni grille tarifaire.
 */
#[ORM\Entity(repositoryClass: ModeLivraisonRepository::class)]
#[UniqueEntity(fields: ['code'], message: 'Ce code de mode existe déjà.')]
class ModeLivraison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    /** Identifiant stable envoyé par le front, ex. domicile, retrait_atelier */
    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    #[Assert\Regex('/^[a-z0-9_]+$/', message: 'Minuscules, chiffres et « _ » uniquement.')]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    /** Affichée au client sous le nom (transporteur, signature, assurance…) */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $description = null;

    #[ORM\Column(name: 'is_retrait', options: ['default' => false])]
    private bool $retrait = false;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    public function __toString(): string
    {
        return (string) $this->nom;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isRetrait(): bool
    {
        return $this->retrait;
    }

    public function setRetrait(bool $retrait): static
    {
        $this->retrait = $retrait;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

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
