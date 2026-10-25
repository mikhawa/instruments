<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ZoneLivraisonRepository;
use App\Validator\PaysDansUneSeuleZone;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Zone tarifaire de livraison : un ensemble de pays (ex. BE, UE proche, reste de l'UE).
 * Un pays appartient à une seule zone (contrainte PaysDansUneSeuleZone).
 */
#[ORM\Entity(repositoryClass: ZoneLivraisonRepository::class)]
#[UniqueEntity(fields: ['code'], message: 'Ce code de zone existe déjà.')]
#[PaysDansUneSeuleZone]
class ZoneLivraison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    /** Ex. BE, UE1, UE2 */
    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    /** @var list<string> codes ISO 3166-1 alpha-2 */
    #[ORM\Column]
    #[Assert\Count(min: 1, minMessage: 'Une zone contient au moins un pays.')]
    #[Assert\All([new Assert\Country()])]
    private array $pays = [];

    public function __toString(): string
    {
        return (string) $this->nom;
    }

    public function contientPays(string $pays): bool
    {
        return \in_array(strtoupper($pays), $this->pays, true);
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
        $this->code = strtoupper($code);

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

    /**
     * @return list<string>
     */
    public function getPays(): array
    {
        return $this->pays;
    }

    /**
     * @param list<string> $pays
     */
    public function setPays(array $pays): static
    {
        $this->pays = array_values(array_unique(array_map('strtoupper', $pays)));
        sort($this->pays);

        return $this;
    }
}
