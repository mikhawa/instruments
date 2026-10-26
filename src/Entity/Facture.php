<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypeFacture;
use App\Repository\FactureRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Facture ou note de crédit, **figée** à l'émission (prepare/06-fiscalite-et-juridique.md § 2) :
 * vendeur, client, lignes et totaux sont copiés ; rien n'est relu depuis la commande ou le catalogue.
 * Une facture émise n'est jamais modifiée ni supprimée : un remboursement donne une note de crédit.
 * Le PDF est produit à la demande à partir de ces données (GenerateurPdfFacture), toujours identique.
 * Montants en centimes, toujours positifs (une note de crédit crédite le montant indiqué).
 */
#[ORM\Entity(repositoryClass: FactureRepository::class)]
#[ORM\Index(name: 'idx_facture_date', columns: ['date_emission'])]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    /** F2026-000001, NC2026-000001 : continu, sans trou, par série et par année */
    #[ORM\Column(length: 20, unique: true)]
    private string $numero;

    #[ORM\Column(length: 20, enumType: TypeFacture::class)]
    private TypeFacture $type;

    #[ORM\ManyToOne(inversedBy: 'factures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Commande $commande;

    /** Note de crédit : facture qu'elle corrige */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?Facture $factureOrigine;

    /** Note de crédit : remboursement qu'elle documente */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Remboursement $remboursement;

    #[ORM\Column]
    private \DateTimeImmutable $dateEmission;

    /** Langue du document (celle de la commande) */
    #[ORM\Column(length: 5)]
    private string $locale;

    /**
     * Identité du vendeur au jour de l'émission (nom, adresse, numéro de TVA, BCE, e-mail, IBAN).
     *
     * @var array<string, string|list<string>|null>
     */
    #[ORM\Column]
    private array $vendeur;

    /**
     * Client : nom, société, adresse de facturation, e-mail.
     *
     * @var array<string, string|null>
     */
    #[ORM\Column]
    private array $client;

    /**
     * Lignes figées : reference, libelle, quantite, prixUnitaireHt, tauxTva (NULL sous le régime
     * de la marge), totalHt, totalTva, totalTtc, regimeTva.
     *
     * @var list<array<string, mixed>>
     */
    #[ORM\Column]
    private array $lignes;

    /**
     * Ventilation de la TVA par taux : [{taux, base, tva}] (régime normal seulement).
     *
     * @var list<array{taux: int, base: int, tva: int}>
     */
    #[ORM\Column]
    private array $ventilationTva;

    /**
     * Mentions légales à imprimer (clés de traduction du domaine « factures »).
     *
     * @var list<string>
     */
    #[ORM\Column]
    private array $mentions;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $totalHt;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $totalTva;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $totalTtc;

    /** Facture acquittée : date et moyen du paiement (« Payée le … par … ») */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $payeeAt;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $moyenPaiement;

    /** Note de crédit : motif du remboursement */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif;

    /**
     * @param array<string, string|list<string>|null>     $vendeur
     * @param array<string, string|null>                  $client
     * @param list<array<string, mixed>>                  $lignes
     * @param list<array{taux: int, base: int, tva: int}> $ventilationTva
     * @param list<string>                                $mentions
     */
    public function __construct(
        string $numero,
        TypeFacture $type,
        Commande $commande,
        array $vendeur,
        array $client,
        array $lignes,
        array $ventilationTva,
        array $mentions,
        int $totalHt,
        int $totalTva,
        int $totalTtc,
        ?\DateTimeImmutable $payeeAt = null,
        ?string $moyenPaiement = null,
        ?Facture $factureOrigine = null,
        ?Remboursement $remboursement = null,
        ?string $motif = null,
    ) {
        if ($totalHt + $totalTva !== $totalTtc) {
            throw new \DomainException(\sprintf('Totaux incohérents : %d + %d ≠ %d.', $totalHt, $totalTva, $totalTtc));
        }
        $this->numero = $numero;
        $this->type = $type;
        $this->commande = $commande;
        $this->locale = $commande->getLocale();
        $this->vendeur = $vendeur;
        $this->client = $client;
        $this->lignes = $lignes;
        $this->ventilationTva = $ventilationTva;
        $this->mentions = $mentions;
        $this->totalHt = $totalHt;
        $this->totalTva = $totalTva;
        $this->totalTtc = $totalTtc;
        $this->payeeAt = $payeeAt;
        $this->moyenPaiement = $moyenPaiement;
        $this->factureOrigine = $factureOrigine;
        $this->remboursement = $remboursement;
        $this->motif = null !== $motif ? mb_substr($motif, 0, 255) : null;
        $this->dateEmission = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $commande->addFacture($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): string
    {
        return $this->numero;
    }

    public function getType(): TypeFacture
    {
        return $this->type;
    }

    public function getCommande(): Commande
    {
        return $this->commande;
    }

    public function getFactureOrigine(): ?Facture
    {
        return $this->factureOrigine;
    }

    public function getRemboursement(): ?Remboursement
    {
        return $this->remboursement;
    }

    public function getDateEmission(): \DateTimeImmutable
    {
        return $this->dateEmission;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return array<string, string|list<string>|null>
     */
    public function getVendeur(): array
    {
        return $this->vendeur;
    }

    /**
     * @return array<string, string|null>
     */
    public function getClient(): array
    {
        return $this->client;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLignes(): array
    {
        return $this->lignes;
    }

    /**
     * @return list<array{taux: int, base: int, tva: int}>
     */
    public function getVentilationTva(): array
    {
        return $this->ventilationTva;
    }

    /**
     * @return list<string>
     */
    public function getMentions(): array
    {
        return $this->mentions;
    }

    public function getTotalHt(): int
    {
        return $this->totalHt;
    }

    public function getTotalTva(): int
    {
        return $this->totalTva;
    }

    public function getTotalTtc(): int
    {
        return $this->totalTtc;
    }

    public function getPayeeAt(): ?\DateTimeImmutable
    {
        return $this->payeeAt;
    }

    public function getMoyenPaiement(): ?string
    {
        return $this->moyenPaiement;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function isNoteCredit(): bool
    {
        return TypeFacture::NoteCredit === $this->type;
    }

    public function __toString(): string
    {
        return $this->numero;
    }
}
