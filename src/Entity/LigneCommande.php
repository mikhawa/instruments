<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LigneCommandeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ligne de commande. Référence, libellé, prix et TVA sont recopiés depuis
 * l'instrument : la ligne reste exacte même si l'instrument change ou disparaît.
 */
#[ORM\Entity(repositoryClass: LigneCommandeRepository::class)]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Instrument $instrument;

    #[ORM\Column(length: 30)]
    private string $reference;

    #[ORM\Column(length: 150)]
    private string $libelle;

    /** Centimes */
    #[ORM\Column(options: ['unsigned' => true])]
    private int $prixUnitaireHt;

    /** Points de base */
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $tauxTva;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Positive]
    private int $quantite;

    /** Centimes : prix_unitaire_ht × quantite */
    #[ORM\Column(options: ['unsigned' => true])]
    private int $totalHt;

    /**
     * Crée une ligne en figeant les données de l'instrument dans la langue de la commande.
     */
    public function __construct(Instrument $instrument, int $quantite, string $locale)
    {
        if ($quantite <= 0) {
            throw new \InvalidArgumentException('La quantité doit être strictement positive.');
        }

        $this->instrument = $instrument;
        $this->reference = (string) $instrument->getReference();
        $this->libelle = (string) ($instrument->getTraduction($locale)?->getNom()
            ?? $instrument->getTraduction('fr')?->getNom()
            ?? $instrument->getReference());
        $this->prixUnitaireHt = (int) $instrument->getPrixHt();
        $this->tauxTva = $instrument->getTauxTva();
        $this->quantite = $quantite;
        $this->totalHt = $this->prixUnitaireHt * $quantite;
    }

    /**
     * Montant de TVA de la ligne en centimes, arrondi au centime.
     */
    public function getMontantTva(): int
    {
        return (int) round($this->totalHt * $this->tauxTva / 10000);
    }

    /**
     * Ex. « 2 × Oud de Bagdad (CRD-OUD-0042) ».
     */
    public function __toString(): string
    {
        return sprintf('%d × %s (%s)', $this->quantite, $this->libelle, $this->reference);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;

        return $this;
    }

    public function getInstrument(): ?Instrument
    {
        return $this->instrument;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getPrixUnitaireHt(): int
    {
        return $this->prixUnitaireHt;
    }

    public function getTauxTva(): int
    {
        return $this->tauxTva;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function getTotalHt(): int
    {
        return $this->totalHt;
    }
}
