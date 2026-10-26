<?php

declare(strict_types=1);

namespace App\Service\Facturation;

use App\Entity\Commande;
use App\Entity\Facture;
use App\Entity\Remboursement;
use App\Enum\RegimeTva;
use App\Enum\TypeFacture;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Émet les factures et les notes de crédit, figées à partir de la commande.
 *
 * HYPOTHÈSES À VALIDER AVEC LE COMPTABLE (prepare/README.md, étape 0.2) :
 *  - les factures sont produites par le site (pas par un logiciel comptable) ;
 *  - TVA belge pour toutes les ventes (pas d'OSS) ;
 *  - frais de port au taux normal belge (21 %), sauf commande entièrement sous régime de la marge :
 *    ils suivent alors le régime des biens (aucune TVA visible) ;
 *  - note de crédit partielle : TVA ventilée au prorata de la facture d'origine.
 *
 * À appeler dans la transaction qui enregistre le paiement ou le remboursement (numérotation sans trou).
 */
final readonly class EmissionFacture
{
    /** Taux normal belge appliqué aux frais de port, en points de base */
    public const TAUX_PORT = 2100;

    public function __construct(
        private NumerotationFacture $numerotation,
        private Vendeur $vendeur,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Facture de la commande payée ; la même facture si elle existe déjà.
     */
    public function facturer(Commande $commande): Facture
    {
        $existante = $this->factureDe($commande);
        if (null !== $existante) {
            return $existante;
        }

        $lignes = [];
        $ventilation = [];
        $mentions = [];
        $regimeNormalPresent = false;

        foreach ($commande->getLignes() as $ligne) {
            $regime = $ligne->getRegimeTva();
            if ($regime->isMarge()) {
                $mentions[] = 'mention.'.$regime->value;
                $lignes[] = $this->ligne($ligne->getReference(), $ligne->getLibelle(), $ligne->getQuantite(), $ligne->getPrixUnitaireTtc(), null, $ligne->getTotalTtc(), 0, $regime);
                continue;
            }
            $regimeNormalPresent = true;
            $tva = $ligne->getTotalTtc() - $ligne->getTotalHt();
            $lignes[] = $this->ligne($ligne->getReference(), $ligne->getLibelle(), $ligne->getQuantite(), $ligne->getPrixUnitaireHt(), $ligne->getTauxTva(), $ligne->getTotalHt(), $tva, $regime);
            $this->ventiler($ventilation, $ligne->getTauxTva(), $ligne->getTotalHt(), $tva);
        }

        $port = $commande->getFraisPortTtc();
        if ($port > 0) {
            $libelle = $commande->getLibelleLivraison() ?? 'Livraison';
            if ($regimeNormalPresent) {
                $ht = (int) round($port * 10000 / (10000 + self::TAUX_PORT));
                $lignes[] = $this->ligne('PORT', $libelle, 1, $ht, self::TAUX_PORT, $ht, $port - $ht, RegimeTva::Normal);
                $this->ventiler($ventilation, self::TAUX_PORT, $ht, $port - $ht);
            } else {
                $lignes[] = $this->ligne('PORT', $libelle, 1, $port, null, $port, 0, RegimeTva::MargeOccasion);
            }
        }

        $totalTva = array_sum(array_column($ventilation, 'tva'));
        $dernierPaiement = null;
        foreach ($commande->getPaiements() as $paiement) {
            if ($paiement->getStatut()->isEncaisse()) {
                $dernierPaiement = $paiement;
            }
        }

        return $this->emettre(new Facture(
            numero: $this->numerotation->suivant(TypeFacture::Facture, new \DateTimeImmutable()),
            type: TypeFacture::Facture,
            commande: $commande,
            vendeur: $this->vendeur->enTableau(),
            client: $this->client($commande),
            lignes: $lignes,
            ventilationTva: array_values($ventilation),
            mentions: array_values(array_unique($mentions)),
            totalHt: $commande->getTotalTtc() - $totalTva,
            totalTva: $totalTva,
            totalTtc: $commande->getTotalTtc(),
            payeeAt: $commande->getPaidAt(),
            moyenPaiement: $dernierPaiement?->getMoyen(),
        ));
    }

    /**
     * Note de crédit pour un remboursement de $montant ; NULL si la commande n'a pas été facturée
     * (paiement remboursé avant toute facture : paiement en double, reçu après l'annulation).
     *
     * @throws \DomainException montant supérieur à ce qui reste à créditer
     */
    public function noteDeCredit(Commande $commande, int $montant, string $motif, ?Remboursement $remboursement = null): ?Facture
    {
        $origine = $this->factureDe($commande);
        if (null === $origine) {
            return null;
        }
        $dejaCredite = 0;
        foreach ($commande->getFactures() as $facture) {
            if ($facture->isNoteCredit()) {
                $dejaCredite += $facture->getTotalTtc();
            }
        }
        if ($montant <= 0 || $montant > $origine->getTotalTtc() - $dejaCredite) {
            throw new \DomainException(\sprintf('Note de crédit impossible : %d centimes demandés, %d restent à créditer.', $montant, $origine->getTotalTtc() - $dejaCredite));
        }

        if (0 === $dejaCredite && $montant === $origine->getTotalTtc()) {
            // Remboursement intégral : reprise exacte de la facture
            $lignes = $origine->getLignes();
            $ventilation = $origine->getVentilationTva();
        } else {
            $ventilation = array_map(static fn (array $v): array => [
                'taux' => $v['taux'],
                'base' => (int) round($v['base'] * $montant / $origine->getTotalTtc()),
                'tva' => (int) round($v['tva'] * $montant / $origine->getTotalTtc()),
            ], $origine->getVentilationTva());
            $tva = array_sum(array_column($ventilation, 'tva'));
            $taux = 1 === \count($ventilation) ? $ventilation[0]['taux'] : null;
            $lignes = [$this->ligne('REMB', $motif, 1, $montant - $tva, $taux, $montant - $tva, $tva, RegimeTva::Normal)];
        }
        $totalTva = array_sum(array_column($ventilation, 'tva'));

        return $this->emettre(new Facture(
            numero: $this->numerotation->suivant(TypeFacture::NoteCredit, new \DateTimeImmutable()),
            type: TypeFacture::NoteCredit,
            commande: $commande,
            vendeur: $this->vendeur->enTableau(),
            client: $origine->getClient(),
            lignes: $lignes,
            ventilationTva: $ventilation,
            mentions: $origine->getMentions(),
            totalHt: $montant - $totalTva,
            totalTva: $totalTva,
            totalTtc: $montant,
            factureOrigine: $origine,
            remboursement: $remboursement,
            motif: $motif,
        ));
    }

    public function factureDe(Commande $commande): ?Facture
    {
        foreach ($commande->getFactures() as $facture) {
            if (!$facture->isNoteCredit()) {
                return $facture;
            }
        }

        return null;
    }

    private function emettre(Facture $facture): Facture
    {
        $this->entityManager->persist($facture);

        return $facture;
    }

    /**
     * @param array<int, array{taux: int, base: int, tva: int}> $ventilation
     */
    private function ventiler(array &$ventilation, int $taux, int $base, int $tva): void
    {
        $ventilation[$taux] ??= ['taux' => $taux, 'base' => 0, 'tva' => 0];
        $ventilation[$taux]['base'] += $base;
        $ventilation[$taux]['tva'] += $tva;
    }

    /**
     * @return array<string, mixed>
     */
    private function ligne(string $reference, string $libelle, int $quantite, int $prixUnitaire, ?int $taux, int $totalHt, int $totalTva, RegimeTva $regime): array
    {
        return [
            'reference' => $reference,
            'libelle' => $libelle,
            'quantite' => $quantite,
            'prixUnitaireHt' => $prixUnitaire,
            'tauxTva' => $taux,
            'totalHt' => $totalHt,
            'totalTva' => $totalTva,
            'totalTtc' => $totalHt + $totalTva,
            'regimeTva' => $regime->value,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function client(Commande $commande): array
    {
        $adresse = $commande->getAdresseFacturation();

        return [
            'nom' => trim(($adresse['prenom'] ?? '').' '.($adresse['nom'] ?? '')),
            'societe' => $adresse['societe'] ?? null,
            'rue' => $adresse['rue'] ?? null,
            'complement' => $adresse['complement'] ?? null,
            'codePostal' => $adresse['codePostal'] ?? null,
            'ville' => $adresse['ville'] ?? null,
            'pays' => $adresse['pays'] ?? null,
            'email' => $commande->getEmail(),
        ];
    }
}
