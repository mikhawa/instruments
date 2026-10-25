<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LignePanierRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Instrument placé dans un panier. Seule la quantité compte : le prix HT
 * mémorisé à l'ajout sert uniquement à signaler un changement de prix.
 */
#[ORM\Entity(repositoryClass: LignePanierRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_ligne_panier_instrument', columns: ['panier_id', 'instrument_id'])]
class LignePanier
{
    /** Plafond par ligne, en plus de la quantité disponible */
    public const QUANTITE_MAX = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Panier $panier;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Instrument $instrument;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $quantite;

    /** Centimes, prix HT au moment de l'ajout */
    #[ORM\Column(options: ['unsigned' => true])]
    private int $prixHtAjout;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Panier $panier, Instrument $instrument, int $quantite)
    {
        $this->panier = $panier;
        $this->instrument = $instrument;
        $this->setQuantite($quantite);
        $this->prixHtAjout = (int) $instrument->getPrixHt();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $panier->ajouterLigne($this);
    }

    /**
     * Quantité maximale commandable pour cet instrument (stock, pièce unique, plafond).
     */
    public static function quantiteMaximale(Instrument $instrument): int
    {
        $plafond = $instrument->isPieceUnique() ? 1 : self::QUANTITE_MAX;

        return max(0, min($plafond, $instrument->getQuantiteDisponible()));
    }

    public function isPrixModifie(): bool
    {
        return $this->prixHtAjout !== $this->instrument->getPrixHt();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPanier(): Panier
    {
        return $this->panier;
    }

    public function getInstrument(): Instrument
    {
        return $this->instrument;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        if ($quantite <= 0) {
            throw new \InvalidArgumentException('La quantité doit être strictement positive.');
        }
        $this->quantite = $quantite;

        return $this;
    }

    public function getPrixHtAjout(): int
    {
        return $this->prixHtAjout;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
