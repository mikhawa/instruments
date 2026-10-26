<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MotifAnnulation;
use App\Enum\StatutCommande;
use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Commande client, avec ou sans compte (invité : utilisateur NULL, e-mail obligatoire).
 * Les adresses et le mode de livraison sont des copies figées au moment de l'achat.
 * Montants en centimes. Le statut évolue uniquement via Symfony Workflow.
 */
#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Index(name: 'idx_commande_utilisateur_date', columns: ['utilisateur_id', 'created_at'])]
#[ORM\Index(name: 'idx_commande_statut_date', columns: ['statut', 'created_at'])]
#[ORM\Index(name: 'idx_commande_statut_expiration', columns: ['statut', 'expire_at'])]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    /** Ex. CMD-2026-000123 */
    #[ORM\Column(length: 20, unique: true)]
    private ?string $numero = null;

    public const CANAL_SITE = 'site';

    /** NULL = commande invité ; RESTRICT : une commande doit être conservée (obligations comptables) */
    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Utilisateur $utilisateur = null;

    /** Adresse de contact (celle du compte, ou saisie par l'invité) */
    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $telephone = null;

    /** Clé secrète du lien « suivre ma commande » envoyé par e-mail (UUID v4 aléatoire) */
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $jetonSuivi;

    /** Fin de la réservation du stock tant que la commande n'est pas payée */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expireAt = null;

    /** Version des CGV acceptées et date d'acceptation (preuve du consentement) */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $cgvVersion = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cgvAccepteesAt = null;

    /** Origine : site (plus tard : admin, reverb…) */
    #[ORM\Column(length: 20, options: ['default' => self::CANAL_SITE])]
    private string $canal = self::CANAL_SITE;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ModeLivraison $modeLivraison = null;

    /** Copie du nom du mode de livraison */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $libelleLivraison = null;

    /** Panier d'origine : une nouvelle commande du même panier annule la précédente non payée */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Panier $panier = null;

    /** En-tête Idempotency-Key : un double envoi renvoie la même commande */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $cleIdempotence = null;

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

    /** Posé par HorodatageCommandeListener ; décide du sort d'un paiement reçu après l'annulation */
    #[ORM\Column(length: 20, nullable: true, enumType: MotifAnnulation::class)]
    private ?MotifAnnulation $motifAnnulation = null;

    /** @var Collection<int, Paiement> */
    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'commande')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $paiements;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->paiements = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->jetonSuivi = Uuid::v4();
    }

    /**
     * Recalcule HT, TVA et TTC à partir des lignes et des frais de port.
     */
    public function recalculerTotaux(): void
    {
        $totalHt = 0;
        $totalTtcLignes = 0;
        foreach ($this->lignes as $ligne) {
            $totalHt += $ligne->getTotalHt();
            $totalTtcLignes += $ligne->getTotalTtc();
        }
        $this->totalHt = $totalHt;
        $this->totalTva = $totalTtcLignes - $totalHt;
        $this->totalTtc = $totalTtcLignes + $this->fraisPortTtc;
    }

    /**
     * Libellé du client : nom du compte, sinon e-mail de l'invité.
     */
    public function getNomClient(): string
    {
        return null !== $this->utilisateur
            ? trim($this->utilisateur->getPrenom().' '.$this->utilisateur->getNom())
            : (string) $this->email;
    }

    public function isInvite(): bool
    {
        return null === $this->utilisateur;
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

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        if (null !== $utilisateur) {
            $this->email = $utilisateur->getEmail();
        }

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getJetonSuivi(): Uuid
    {
        return $this->jetonSuivi;
    }

    public function getExpireAt(): ?\DateTimeImmutable
    {
        return $this->expireAt;
    }

    public function setExpireAt(?\DateTimeImmutable $expireAt): static
    {
        $this->expireAt = $expireAt;

        return $this;
    }

    public function getCgvVersion(): ?string
    {
        return $this->cgvVersion;
    }

    public function getCgvAccepteesAt(): ?\DateTimeImmutable
    {
        return $this->cgvAccepteesAt;
    }

    public function accepterCgv(string $version): static
    {
        $this->cgvVersion = $version;
        $this->cgvAccepteesAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    public function getCanal(): string
    {
        return $this->canal;
    }

    public function getModeLivraison(): ?ModeLivraison
    {
        return $this->modeLivraison;
    }

    public function getLibelleLivraison(): ?string
    {
        return $this->libelleLivraison;
    }

    /**
     * Mode de livraison choisi et frais de port correspondants.
     */
    public function choisirLivraison(ModeLivraison $mode, int $fraisPortTtc): static
    {
        $this->modeLivraison = $mode;
        $this->libelleLivraison = $mode->getNom();

        return $this->setFraisPortTtc($fraisPortTtc);
    }

    public function getPanier(): ?Panier
    {
        return $this->panier;
    }

    public function setPanier(?Panier $panier): static
    {
        $this->panier = $panier;

        return $this;
    }

    public function getCleIdempotence(): ?string
    {
        return $this->cleIdempotence;
    }

    public function setCleIdempotence(?string $cleIdempotence): static
    {
        $this->cleIdempotence = $cleIdempotence;

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

    public function __toString(): string
    {
        return (string) $this->numero;
    }

    public function getMotifAnnulation(): ?MotifAnnulation
    {
        return $this->motifAnnulation;
    }

    public function setMotifAnnulation(?MotifAnnulation $motifAnnulation): static
    {
        $this->motifAnnulation = $motifAnnulation;

        return $this;
    }

    /**
     * @return Collection<int, Paiement>
     */
    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    /**
     * Appelé par le constructeur de Paiement.
     */
    public function addPaiement(Paiement $paiement): static
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements->add($paiement);
        }

        return $this;
    }

    /**
     * Dernière tentative de paiement (la plus récente), ou NULL.
     */
    public function getDernierPaiement(): ?Paiement
    {
        $dernier = $this->paiements->last();

        return false !== $dernier ? $dernier : null;
    }
}
