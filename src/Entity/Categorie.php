<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\CategorieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catégorie du catalogue, organisée en arborescence.
 * Lecture publique (catégories actives), écriture réservée aux administrateurs.
 */
#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['categorie:read']],
    denormalizationContext: ['groups' => ['categorie:write']],
    order: ['position' => 'ASC'],
    paginationEnabled: false,
)]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    #[Groups(['categorie:read', 'instrument:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'enfants')]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    #[Groups(['categorie:read', 'categorie:write', 'instrument:read'])]
    private ?Categorie $parent = null;

    /** @var Collection<int, Categorie> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $enfants;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    #[Groups(['categorie:read', 'categorie:write'])]
    private int $position = 0;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    #[Groups(['categorie:read', 'categorie:write'])]
    private bool $active = true;

    /** @var Collection<int, CategorieTraduction> */
    #[ORM\OneToMany(targetEntity: CategorieTraduction::class, mappedBy: 'categorie', cascade: ['persist'], orphanRemoval: true, indexBy: 'locale')]
    #[Groups(['categorie:read', 'categorie:write', 'instrument:read'])]
    #[Assert\Count(min: 1, minMessage: 'Au moins une traduction est requise.')]
    #[Assert\Valid]
    private Collection $traductions;

    /** @var Collection<int, Instrument> */
    #[ORM\OneToMany(targetEntity: Instrument::class, mappedBy: 'categorie')]
    private Collection $instruments;

    public function __construct()
    {
        $this->enfants = new ArrayCollection();
        $this->traductions = new ArrayCollection();
        $this->instruments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, Categorie>
     */
    public function getEnfants(): Collection
    {
        return $this->enfants;
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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection<string, CategorieTraduction>
     */
    public function getTraductions(): Collection
    {
        return $this->traductions;
    }

    public function getTraduction(string $locale): ?CategorieTraduction
    {
        return $this->traductions->get($locale);
    }

    public function addTraduction(CategorieTraduction $traduction): static
    {
        if (!$this->traductions->contains($traduction)) {
            $this->traductions->set((string) $traduction->getLocale(), $traduction);
            $traduction->setCategorie($this);
        }

        return $this;
    }

    public function removeTraduction(CategorieTraduction $traduction): static
    {
        $this->traductions->removeElement($traduction);

        return $this;
    }

    /**
     * @return Collection<int, Instrument>
     */
    public function getInstruments(): Collection
    {
        return $this->instruments;
    }
}
