<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RemboursementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Remboursement (total ou partiel) d'un paiement, sur le même moyen de paiement.
 * Il est d'abord enregistré « à effectuer » dans la transaction qui le décide,
 * puis envoyé au prestataire après la validation (RemboursementPaiement) : un échec réseau
 * ne perd donc jamais un remboursement dû, la synchronisation planifiée le relance.
 */
#[ORM\Entity(repositoryClass: RemboursementRepository::class)]
#[ORM\Index(name: 'idx_remboursement_statut', columns: ['statut'])]
class Remboursement
{
    public const STATUT_A_EFFECTUER = 'a_effectuer';
    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_EFFECTUE = 'effectue';
    public const STATUT_ECHOUE = 'echoue';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'remboursements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Paiement $paiement;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $montant;

    #[ORM\Column(length: 255)]
    private string $motif;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $idExterne = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_A_EFFECTUER;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $erreur = null;

    /** Administrateur qui l'a demandé ; NULL pour un remboursement automatique */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateur;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $effectueAt = null;

    public function __construct(Paiement $paiement, int $montant, string $motif, ?Utilisateur $utilisateur = null)
    {
        if ($montant <= 0 || $montant > $paiement->getMontant() - $paiement->getMontantRembourse()) {
            throw new \DomainException('Montant de remboursement invalide.');
        }
        $this->paiement = $paiement;
        $this->montant = $montant;
        $this->motif = mb_substr($motif, 0, 255);
        $this->utilisateur = $utilisateur;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $paiement->addRemboursement($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPaiement(): Paiement
    {
        return $this->paiement;
    }

    public function getMontant(): int
    {
        return $this->montant;
    }

    public function getMotif(): string
    {
        return $this->motif;
    }

    public function getIdExterne(): ?string
    {
        return $this->idExterne;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function getErreur(): ?string
    {
        return $this->erreur;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEffectueAt(): ?\DateTimeImmutable
    {
        return $this->effectueAt;
    }

    /**
     * Envoi au prestataire en cours : un second processus ne le reprendra pas.
     */
    public function prendreEnCharge(): static
    {
        $this->statut = self::STATUT_EN_COURS;

        return $this;
    }

    /**
     * Panne passagère : sera renvoyé par la synchronisation planifiée.
     */
    public function aRelancer(string $erreur): static
    {
        $this->statut = self::STATUT_A_EFFECTUER;
        $this->erreur = mb_substr($erreur, 0, 255);

        return $this;
    }

    public function envoye(string $idExterne, bool $effectue): static
    {
        $this->idExterne = $idExterne;
        $this->statut = $effectue ? self::STATUT_EFFECTUE : self::STATUT_EN_COURS;
        $this->erreur = null;
        if ($effectue) {
            $this->effectueAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }

        return $this;
    }

    public function echec(string $erreur): static
    {
        $this->statut = self::STATUT_ECHOUE;
        $this->erreur = mb_substr($erreur, 0, 255);

        return $this;
    }

    public function __toString(): string
    {
        return \sprintf('%s € — %s (%s)', number_format($this->montant / 100, 2, ',', ' '), $this->motif, $this->statut);
    }
}
