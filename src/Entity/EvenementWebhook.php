<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PrestatairePaiement;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal des notifications reçues des prestataires de paiement (audit et diagnostic).
 *
 * Mollie n'envoie que l'identifiant du paiement, sans identifiant d'événement : la même
 * référence revient à chaque changement de statut. L'idempotence ne repose donc pas sur ce
 * journal mais sur le traitement lui-même, qui relit le statut et ne l'applique qu'une fois
 * (EncaissementPaiement, sous verrou).
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_evenement_webhook_reference', columns: ['reference'])]
class EvenementWebhook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: PrestatairePaiement::class)]
    private PrestatairePaiement $prestataire;

    /** Identifiant transmis par le prestataire (tr_… chez Mollie) */
    #[ORM\Column(length: 100)]
    private string $reference;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $chargeUtile;

    #[ORM\Column]
    private \DateTimeImmutable $recuAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $traiteAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $erreur = null;

    /**
     * @param array<string, mixed> $chargeUtile
     */
    public function __construct(PrestatairePaiement $prestataire, string $reference, array $chargeUtile)
    {
        $this->prestataire = $prestataire;
        $this->reference = $reference;
        $this->chargeUtile = $chargeUtile;
        $this->recuAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrestataire(): PrestatairePaiement
    {
        return $this->prestataire;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @return array<string, mixed>
     */
    public function getChargeUtile(): array
    {
        return $this->chargeUtile;
    }

    public function getRecuAt(): \DateTimeImmutable
    {
        return $this->recuAt;
    }

    public function getTraiteAt(): ?\DateTimeImmutable
    {
        return $this->traiteAt;
    }

    public function getErreur(): ?string
    {
        return $this->erreur;
    }

    public function echec(string $erreur): static
    {
        $this->erreur = mb_substr($erreur, 0, 255);

        return $this;
    }

    public function traite(?string $erreur = null): static
    {
        $this->traiteAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->erreur = null !== $erreur ? mb_substr($erreur, 0, 255) : null;

        return $this;
    }
}
