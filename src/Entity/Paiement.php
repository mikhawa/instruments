<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PrestatairePaiement;
use App\Enum\StatutPaiement;
use App\Repository\PaiementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tentative de paiement d'une commande (une commande peut en avoir plusieurs : échec, nouvel essai, virement…).
 * Le statut ne fait foi qu'une fois lu chez le prestataire (SynchronisationPaiement), jamais sur la parole du client.
 * Montants en centimes. Aucune donnée de carte n'est conservée.
 */
#[ORM\Entity(repositoryClass: PaiementRepository::class)]
#[ORM\Index(name: 'idx_paiement_statut_date', columns: ['statut', 'created_at'])]
class Paiement
{
    public const DEVISE = 'EUR';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Commande $commande;

    #[ORM\Column(length: 20, enumType: PrestatairePaiement::class)]
    private PrestatairePaiement $prestataire;

    /** Moyen réellement utilisé, connu après le paiement (bancontact, creditcard, paypal, virement…) */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $moyen = null;

    /** Identifiant chez le prestataire (tr_… chez Mollie) */
    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $idExterne = null;

    /** Page de paiement du prestataire, réutilisable tant que le paiement est ouvert */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $urlPaiement = null;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $montant;

    #[ORM\Column(length: 3, options: ['fixed' => true, 'default' => self::DEVISE])]
    private string $devise = self::DEVISE;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 0])]
    private int $montantRembourse = 0;

    #[ORM\Column(length: 30, enumType: StatutPaiement::class)]
    private StatutPaiement $statut = StatutPaiement::Ouvert;

    /** Communication structurée belge d'un virement : +++123/4567/89012+++ */
    #[ORM\Column(length: 21, unique: true, nullable: true)]
    private ?string $referenceVirement = null;

    /**
     * Informations utiles renvoyées par le prestataire (libellé et pays de la carte, anomalie détectée…).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(nullable: true)]
    private ?array $donnees = null;

    /** @var Collection<int, Remboursement> */
    #[ORM\OneToMany(targetEntity: Remboursement::class, mappedBy: 'paiement')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $remboursements;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $payeAt = null;

    public function __construct(Commande $commande, PrestatairePaiement $prestataire, int $montant)
    {
        if ($montant <= 0) {
            throw new \DomainException('Le montant d\'un paiement doit être positif.');
        }
        $this->commande = $commande;
        $this->prestataire = $prestataire;
        $this->montant = $montant;
        $this->remboursements = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
        $commande->addPaiement($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): Commande
    {
        return $this->commande;
    }

    public function getPrestataire(): PrestatairePaiement
    {
        return $this->prestataire;
    }

    public function getMoyen(): ?string
    {
        return $this->moyen;
    }

    public function setMoyen(?string $moyen): static
    {
        $this->moyen = $moyen;

        return $this;
    }

    public function getIdExterne(): ?string
    {
        return $this->idExterne;
    }

    public function getUrlPaiement(): ?string
    {
        return $this->urlPaiement;
    }

    /**
     * Paiement enregistré chez le prestataire.
     */
    public function ouvrir(string $idExterne, ?string $urlPaiement): static
    {
        $this->idExterne = $idExterne;
        $this->urlPaiement = $urlPaiement;
        $this->toucher();

        return $this;
    }

    public function getMontant(): int
    {
        return $this->montant;
    }

    public function getDevise(): string
    {
        return $this->devise;
    }

    public function getMontantRembourse(): int
    {
        return $this->montantRembourse;
    }

    public function setMontantRembourse(int $montantRembourse): static
    {
        $this->montantRembourse = max(0, min($montantRembourse, $this->montant));
        $this->toucher();

        return $this;
    }

    public function getStatut(): StatutPaiement
    {
        return $this->statut;
    }

    public function setStatut(StatutPaiement $statut): static
    {
        $this->statut = $statut;
        if ($statut->isEncaisse() && null === $this->payeAt) {
            $this->payeAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }
        $this->toucher();

        return $this;
    }

    public function getReferenceVirement(): ?string
    {
        return $this->referenceVirement;
    }

    public function setReferenceVirement(?string $referenceVirement): static
    {
        $this->referenceVirement = $referenceVirement;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDonnees(): array
    {
        return $this->donnees ?? [];
    }

    /**
     * Fusionne de nouvelles informations avec les précédentes.
     *
     * @param array<string, mixed> $donnees
     */
    public function ajouterDonnees(array $donnees): static
    {
        $this->donnees = [...$this->getDonnees(), ...$donnees];
        $this->toucher();

        return $this;
    }

    /**
     * @return Collection<int, Remboursement>
     */
    public function getRemboursements(): Collection
    {
        return $this->remboursements;
    }

    public function addRemboursement(Remboursement $remboursement): static
    {
        if (!$this->remboursements->contains($remboursement)) {
            $this->remboursements->add($remboursement);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPayeAt(): ?\DateTimeImmutable
    {
        return $this->payeAt;
    }

    public function __toString(): string
    {
        return \sprintf('%s n° %s — %s', $this->prestataire->value, $this->idExterne ?? $this->referenceVirement ?? (string) $this->id, $this->statut->value);
    }

    private function toucher(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
