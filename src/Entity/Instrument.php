<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Trait\HorodatageTrait;
use App\Enum\EtatInstrument;
use App\Repository\InstrumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Instrument du catalogue (données non traduisibles).
 * Lecture publique des instruments publiés, écriture réservée aux administrateurs.
 * Montants en centimes, taux de TVA en points de base (2100 = 21 %).
 */
#[ORM\Entity(repositoryClass: InstrumentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_instrument_publication', columns: ['is_published', 'categorie_id'])]
#[ORM\Index(name: 'idx_instrument_pays', columns: ['pays_origine'])]
#[UniqueEntity(fields: ['reference'], message: 'Cette référence existe déjà.')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['instrument:read']],
    denormalizationContext: ['groups' => ['instrument:write']],
    order: ['createdAt' => 'DESC'],
    paginationItemsPerPage: 24,
)]
class Instrument
{
    use HorodatageTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    #[Groups(['instrument:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'instruments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\NotNull]
    private ?Categorie $categorie = null;

    /** SKU interne, ex. CRD-OUD-0042 */
    #[ORM\Column(length: 30, unique: true)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private ?string $reference = null;

    /** Code pays ISO 3166-1 alpha-2 */
    #[ORM\Column(length: 2, nullable: true, options: ['fixed' => true])]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\Country]
    private ?string $paysOrigine = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\Length(max: 100)]
    private ?string $regionOrigine = null;

    /** Luthier ou atelier */
    #[ORM\Column(length: 150, nullable: true)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\Length(max: 150)]
    private ?string $facteur = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\Range(min: 1000, max: 2100)]
    private ?int $anneeFabrication = null;

    #[ORM\Column(length: 20, enumType: EtatInstrument::class)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\NotNull]
    private ?EtatInstrument $etat = null;

    /** Pièce de collection : stock limité à 1 */
    #[ORM\Column(name: 'is_piece_unique', options: ['default' => false])]
    #[Groups(['instrument:read', 'instrument:write'])]
    private bool $pieceUnique = false;

    /** Prix hors TVA en centimes */
    #[ORM\Column(options: ['unsigned' => true])]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private ?int $prixHt = null;

    /** Taux de TVA en points de base (2100 = 21 %) */
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 2100])]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\Range(min: 0, max: 10000)]
    private int $tauxTva = 2100;

    #[ORM\Column(nullable: true, options: ['unsigned' => true])]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\Positive]
    private ?int $poidsGrammes = null;

    /** Texte libre, ex. « 78 × 36 × 18 cm » */
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\Length(max: 100)]
    private ?string $dimensions = null;

    #[ORM\Column(name: 'is_published', options: ['default' => false])]
    #[Groups(['instrument:read', 'instrument:write'])]
    private bool $published = false;

    /** @var Collection<string, InstrumentTraduction> */
    #[ORM\OneToMany(targetEntity: InstrumentTraduction::class, mappedBy: 'instrument', cascade: ['persist'], orphanRemoval: true, indexBy: 'locale')]
    #[Groups(['instrument:read', 'instrument:write'])]
    #[Assert\Count(min: 1, minMessage: 'Au moins une traduction est requise.')]
    #[Assert\Valid]
    private Collection $traductions;

    /** @var Collection<int, InstrumentImage> */
    #[ORM\OneToMany(targetEntity: InstrumentImage::class, mappedBy: 'instrument', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['instrument:read'])]
    private Collection $images;

    #[ORM\OneToOne(targetEntity: Stock::class, mappedBy: 'instrument', cascade: ['persist'])]
    private ?Stock $stock = null;

    public function __construct()
    {
        $this->traductions = new ArrayCollection();
        $this->images = new ArrayCollection();
        // Chaque instrument possède exactement un enregistrement de stock
        $this->stock = new Stock($this);
    }

    /**
     * Prix TTC en centimes, arrondi au centime.
     */
    #[Groups(['instrument:read'])]
    public function getPrixTtc(): ?int
    {
        if (null === $this->prixHt) {
            return null;
        }

        return (int) round($this->prixHt * (10000 + $this->tauxTva) / 10000);
    }

    /**
     * Quantité vendable ; seule information de stock exposée publiquement.
     */
    #[Groups(['instrument:read'])]
    #[SerializedName('disponible')]
    public function getQuantiteDisponible(): int
    {
        return $this->stock?->getQuantiteDisponible() ?? 0;
    }

    /**
     * Libellé du back-office : « RÉFÉRENCE — nom français ».
     */
    public function __toString(): string
    {
        return trim(sprintf('%s — %s', $this->reference, $this->getNom() ?? ''), ' —');
    }

    /**
     * Nom dans la langue demandée, sinon première traduction disponible.
     */
    public function getNom(string $locale = 'fr'): ?string
    {
        return ($this->getTraduction($locale) ?? ($this->traductions->first() ?: null))?->getNom();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = strtoupper($reference);

        return $this;
    }

    public function getPaysOrigine(): ?string
    {
        return $this->paysOrigine;
    }

    public function setPaysOrigine(?string $paysOrigine): static
    {
        $this->paysOrigine = null !== $paysOrigine ? strtoupper($paysOrigine) : null;

        return $this;
    }

    public function getRegionOrigine(): ?string
    {
        return $this->regionOrigine;
    }

    public function setRegionOrigine(?string $regionOrigine): static
    {
        $this->regionOrigine = $regionOrigine;

        return $this;
    }

    public function getFacteur(): ?string
    {
        return $this->facteur;
    }

    public function setFacteur(?string $facteur): static
    {
        $this->facteur = $facteur;

        return $this;
    }

    public function getAnneeFabrication(): ?int
    {
        return $this->anneeFabrication;
    }

    public function setAnneeFabrication(?int $anneeFabrication): static
    {
        $this->anneeFabrication = $anneeFabrication;

        return $this;
    }

    public function getEtat(): ?EtatInstrument
    {
        return $this->etat;
    }

    public function setEtat(EtatInstrument $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function isPieceUnique(): bool
    {
        return $this->pieceUnique;
    }

    public function setPieceUnique(bool $pieceUnique): static
    {
        $this->pieceUnique = $pieceUnique;

        return $this;
    }

    public function getPrixHt(): ?int
    {
        return $this->prixHt;
    }

    public function setPrixHt(int $prixHt): static
    {
        $this->prixHt = $prixHt;

        return $this;
    }

    public function getTauxTva(): int
    {
        return $this->tauxTva;
    }

    public function setTauxTva(int $tauxTva): static
    {
        $this->tauxTva = $tauxTva;

        return $this;
    }

    public function getPoidsGrammes(): ?int
    {
        return $this->poidsGrammes;
    }

    public function setPoidsGrammes(?int $poidsGrammes): static
    {
        $this->poidsGrammes = $poidsGrammes;

        return $this;
    }

    public function getDimensions(): ?string
    {
        return $this->dimensions;
    }

    public function setDimensions(?string $dimensions): static
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    /**
     * @return Collection<string, InstrumentTraduction>
     */
    public function getTraductions(): Collection
    {
        return $this->traductions;
    }

    public function getTraduction(string $locale): ?InstrumentTraduction
    {
        return $this->traductions->get($locale);
    }

    public function addTraduction(InstrumentTraduction $traduction): static
    {
        if (!$this->traductions->contains($traduction)) {
            $this->traductions->set((string) $traduction->getLocale(), $traduction);
            $traduction->setInstrument($this);
        }

        return $this;
    }

    public function removeTraduction(InstrumentTraduction $traduction): static
    {
        $this->traductions->removeElement($traduction);

        return $this;
    }

    /**
     * @return Collection<int, InstrumentImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(InstrumentImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setInstrument($this);
        }

        return $this;
    }

    public function removeImage(InstrumentImage $image): static
    {
        $this->images->removeElement($image);

        return $this;
    }

    public function getStock(): ?Stock
    {
        return $this->stock;
    }
}
