<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * État courant du stock d'un instrument (un enregistrement par instrument).
 *
 * Les méthodes garantissent les invariants : quantite >= 0,
 * 0 <= quantite_reservee <= quantite. Chaque appel doit être accompagné
 * d'un MouvementStock, créé par le service de gestion des stocks.
 */
#[ORM\Entity(repositoryClass: StockRepository::class)]
class Stock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'stock')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private Instrument $instrument;

    /** Quantité physiquement en stock */
    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $quantite = 0;

    /** Quantité bloquée par des commandes non expédiées */
    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $quantiteReservee = 0;

    /** Alerte administrateur si la quantité disponible passe sous ce seuil */
    #[ORM\Column(options: ['default' => 1])]
    #[Assert\PositiveOrZero]
    private int $seuilAlerte = 1;

    /** Ex. « Réserve A-3 » */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $emplacement = null;

    /** Verrou optimiste : empêche deux ventes simultanées du même article */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Instrument $instrument)
    {
        $this->instrument = $instrument;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getQuantiteDisponible(): int
    {
        return $this->quantite - $this->quantiteReservee;
    }

    public function isSousSeuilAlerte(): bool
    {
        return $this->getQuantiteDisponible() <= $this->seuilAlerte;
    }

    /**
     * Réception de marchandise ou retour client.
     */
    public function ajouter(int $quantite): void
    {
        $this->exigerPositif($quantite);
        if ($this->instrument->isPieceUnique() && $this->quantite + $quantite > 1) {
            throw new \DomainException('Une pièce unique ne peut pas avoir un stock supérieur à 1.');
        }
        $this->quantite += $quantite;
        $this->toucher();
    }

    /**
     * Blocage pour une commande en attente de paiement.
     */
    public function reserver(int $quantite): void
    {
        $this->exigerPositif($quantite);
        if ($quantite > $this->getQuantiteDisponible()) {
            throw new \DomainException(\sprintf('Stock insuffisant : %d demandé(s), %d disponible(s).', $quantite, $this->getQuantiteDisponible()));
        }
        $this->quantiteReservee += $quantite;
        $this->toucher();
    }

    /**
     * Annulation d'une réservation.
     */
    public function liberer(int $quantite): void
    {
        $this->exigerPositif($quantite);
        if ($quantite > $this->quantiteReservee) {
            throw new \DomainException('Impossible de libérer plus que la quantité réservée.');
        }
        $this->quantiteReservee -= $quantite;
        $this->toucher();
    }

    /**
     * Expédition d'une quantité préalablement réservée.
     */
    public function sortir(int $quantite): void
    {
        $this->exigerPositif($quantite);
        if ($quantite > $this->quantiteReservee) {
            throw new \DomainException('Seule une quantité réservée peut être expédiée.');
        }
        $this->quantiteReservee -= $quantite;
        $this->quantite -= $quantite;
        $this->toucher();
    }

    /**
     * Correction d'inventaire (casse, perte, recomptage) ; delta signé.
     */
    public function ajuster(int $delta): void
    {
        $nouvelleQuantite = $this->quantite + $delta;
        if ($nouvelleQuantite < $this->quantiteReservee) {
            throw new \DomainException('L\'ajustement rendrait le stock inférieur aux quantités réservées.');
        }
        $this->quantite = $nouvelleQuantite;
        $this->toucher();
    }

    private function exigerPositif(int $quantite): void
    {
        if ($quantite <= 0) {
            throw new \InvalidArgumentException('La quantité doit être strictement positive.');
        }
    }

    private function toucher(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstrument(): Instrument
    {
        return $this->instrument;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function getQuantiteReservee(): int
    {
        return $this->quantiteReservee;
    }

    public function getSeuilAlerte(): int
    {
        return $this->seuilAlerte;
    }

    public function setSeuilAlerte(int $seuilAlerte): static
    {
        $this->seuilAlerte = $seuilAlerte;

        return $this;
    }

    public function getEmplacement(): ?string
    {
        return $this->emplacement;
    }

    public function setEmplacement(?string $emplacement): static
    {
        $this->emplacement = $emplacement;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
