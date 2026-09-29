<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypeMouvementStock;
use App\Repository\MouvementStockRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal immuable des variations de stock (audit, inventaire).
 * Aucun setter : un mouvement erroné se corrige par un nouveau mouvement d'ajustement.
 */
#[ORM\Entity(repositoryClass: MouvementStockRepository::class, readOnly: true)]
#[ORM\Index(name: 'idx_mouvement_stock_instrument_date', columns: ['instrument_id', 'created_at'])]
class MouvementStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Instrument $instrument;

    #[ORM\Column(length: 20, enumType: TypeMouvementStock::class)]
    private TypeMouvementStock $type;

    /** Quantité signée : positive pour une entrée, négative pour une sortie */
    #[ORM\Column]
    private int $quantite;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Commande $commande;

    /** Auteur du mouvement (administrateur), null si automatique */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateur;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Instrument $instrument,
        TypeMouvementStock $type,
        int $quantite,
        ?Commande $commande = null,
        ?Utilisateur $utilisateur = null,
        ?string $commentaire = null,
    ) {
        $this->instrument = $instrument;
        $this->type = $type;
        $this->quantite = $quantite;
        $this->commande = $commande;
        $this->utilisateur = $utilisateur;
        $this->commentaire = $commentaire;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstrument(): Instrument
    {
        return $this->instrument;
    }

    public function getType(): TypeMouvementStock
    {
        return $this->type;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
