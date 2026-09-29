<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatutCommande;
use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Commande client. Les adresses sont des copies figées au moment de l'achat.
 * Montants en centimes. Le statut évolue uniquement via Symfony Workflow.
 */
#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Index(name: 'idx_commande_utilisateur_date', columns: ['utilisateur_id', 'created_at'])]
#[ORM\Index(name: 'idx_commande_statut_date', columns: ['statut', 'created_at'])]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    /** Ex. CMD-2026-000123 */
    #[ORM\Column(length: 20, unique: true)]
    private ?string $numero = null;

    /** RESTRICT : une commande doit être conservée (obligations comptables) */
    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(length: 30, enumType: StatutCommande::class)]
    private StatutCommande $statut = StatutCommande::EnAttentePaiement;

    /** @var array<string, string|null> */
    #[ORM\Column]
    #[Assert\NotBlank]
    private array $adresseLivraison = [];

    /** @var array<string, string|null> */
    #[ORM\Column]
    #[Assert\NotBlank]
    private array $adresseFacturation = [];

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $totalHt = 0;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $totalTva = 0;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    #[Assert\PositiveOrZero]
    private int $fraisPortTtc = 0;

    /** Frais de port inclus */
    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $totalTtc = 0;

    /** Langue du client au moment de la commande */
    #[ORM\Column(length: 5)]
    private string $locale = 'fr';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $commentaireClient = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroSuivi = null;

    /** @var Collection<int, LigneCommande> */
    #[ORM\OneToMany(targetEntity: LigneCommande::class, mappedBy: 'commande', cascade: ['persist'], orphanRemoval: true)]
    #[Assert\Count(min: 1, minMessage: 'Une commande doit contenir au moins un article.')]
    #[Assert\Valid]
    private Collection $lignes;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Recalcule HT, TVA et TTC à partir des lignes et des frais de port.
     */
    public function recalculerTotaux(): void
    {
        $totalHt = 0;
        $totalTva = 0;
        foreach ($this->lignes as $ligne) {
            $totalHt += $ligne->getTotalHt();
            $totalTva += $ligne->getMontantTva();
        }
        $this->totalHt = $totalHt;
        $this->totalTva = $totalTva;
        $this->totalTtc = $totalHt + $totalTva + $this->fraisPortTtc;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getStatut(): StatutCommande
    {
        return $this->statut;
    }

    /**
     * Réservé au Workflow : ne pas appeler directement depuis le code métier.
     */
    public function setStatut(StatutCommande $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * @return array<string, string|null>
     */
    public function getAdresseLivraison(): array
    {
        return $this->adresseLivraison;
    }

    public function setAdresseLivraison(Adresse $adresse): static
    {
        $this->adresseLivraison = $adresse->toArray();

        return $this;
    }

    /**
     * @return array<string, string|null>
     */
    public function getAdresseFacturation(): array
    {
        return $this->adresseFacturation;
    }

    public function setAdresseFacturation(Adresse $adresse): static
    {
        $this->adresseFacturation = $adresse->toArray();

        return $this;
    }

    public function getTotalHt(): int
    {
        return $this->totalHt;
    }

    public function getTotalTva(): int
    {
        return $this->totalTva;
    }

    public function getFraisPortTtc(): int
    {
        return $this->fraisPortTtc;
    }

    public function setFraisPortTtc(int $fraisPortTtc): static
    {
        $this->fraisPortTtc = $fraisPortTtc;
        $this->recalculerTotaux();

        return $this;
    }

    public function getTotalTtc(): int
    {
        return $this->totalTtc;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getCommentaireClient(): ?string
    {
        return $this->commentaireClient;
    }

    public function setCommentaireClient(?string $commentaireClient): static
    {
        $this->commentaireClient = $commentaireClient;

        return $this;
    }

    public function getNumeroSuivi(): ?string
    {
        return $this->numeroSuivi;
    }

    public function setNumeroSuivi(?string $numeroSuivi): static
    {
        $this->numeroSuivi = $numeroSuivi;

        return $this;
    }

    /**
     * @return Collection<int, LigneCommande>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneCommande $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setCommande($this);
            $this->recalculerTotaux();
        }

        return $this;
    }

    public function removeLigne(LigneCommande $ligne): static
    {
        if ($this->lignes->removeElement($ligne)) {
            $this->recalculerTotaux();
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getShippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function setShippedAt(?\DateTimeImmutable $shippedAt): static
    {
        $this->shippedAt = $shippedAt;

        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }
}
