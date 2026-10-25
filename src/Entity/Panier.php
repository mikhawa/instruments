<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\HorodatageTrait;
use App\Repository\PanierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Panier d'un visiteur (reconnu par le jeton de son cookie) ou d'un client connecté.
 * Aucun prix n'y est figé : les montants sont toujours recalculés (CalculateurPanier).
 */
#[ORM\Entity(repositoryClass: PanierRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_panier_mise_a_jour', columns: ['updated_at'])]
class Panier
{
    use HorodatageTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    /** Valeur du cookie « panier » (UUID v7) ; l'id n'est jamais exposé */
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $jeton;

    /** NULL = visiteur ; un seul panier par client */
    #[ORM\OneToOne]
    #[ORM\JoinColumn(unique: true, onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(length: 5)]
    private string $locale;

    /** Relance de panier abandonné (une seule) */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $relanceEnvoyeeAt = null;

    /** @var Collection<int, LignePanier> */
    #[ORM\OneToMany(targetEntity: LignePanier::class, mappedBy: 'panier', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $lignes;

    public function __construct(string $locale = 'fr')
    {
        $this->jeton = Uuid::v7();
        $this->locale = $locale;
        $this->lignes = new ArrayCollection();
    }

    /**
     * Ligne de cet instrument, ou null s'il n'est pas dans le panier.
     */
    public function getLignePour(Instrument $instrument): ?LignePanier
    {
        foreach ($this->lignes as $ligne) {
            if ($ligne->getInstrument() === $instrument) {
                return $ligne;
            }
        }

        return null;
    }

    public function getLigne(int $id): ?LignePanier
    {
        foreach ($this->lignes as $ligne) {
            if ($ligne->getId() === $id) {
                return $ligne;
            }
        }

        return null;
    }

    public function ajouterLigne(LignePanier $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
        }
        $this->actualiserHorodatage();

        return $this;
    }

    public function retirerLigne(LignePanier $ligne): static
    {
        $this->lignes->removeElement($ligne);
        $this->actualiserHorodatage();

        return $this;
    }

    public function vider(): static
    {
        $this->lignes->clear();
        $this->actualiserHorodatage();

        return $this;
    }

    public function isVide(): bool
    {
        return $this->lignes->isEmpty();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJeton(): Uuid
    {
        return $this->jeton;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        $this->actualiserHorodatage();

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getRelanceEnvoyeeAt(): ?\DateTimeImmutable
    {
        return $this->relanceEnvoyeeAt;
    }

    public function setRelanceEnvoyeeAt(?\DateTimeImmutable $relanceEnvoyeeAt): static
    {
        $this->relanceEnvoyeeAt = $relanceEnvoyeeAt;

        return $this;
    }

    /**
     * @return Collection<int, LignePanier>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }
}
