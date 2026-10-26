<?php

declare(strict_types=1);

namespace App\Service\Panier;

use App\ApiResource\Panier\InstrumentResume;
use App\ApiResource\Panier\LignePanierCalculee;
use App\ApiResource\Panier\PanierCalcule;
use App\Entity\Instrument;
use App\Entity\LignePanier;
use App\Entity\Panier;

/**
 * Seule source des montants affichés au client (prix, TVA, totaux).
 * Le tunnel de commande réutilise montantsLigne() pour obtenir exactement les mêmes chiffres.
 *
 * Règle (prepare/02-tunnel-commande.md § 6) :
 *   prix TTC unitaire = arrondi(prix HT × (1 + taux)) ; total ligne = prix TTC unitaire × quantité ;
 *   TVA = TTC − HT, de sorte que HT + TVA = TTC au centime près.
 */
final readonly class CalculateurPanier
{
    /**
     * Panier recalculé ; un panier absent est un panier vide.
     */
    public function calculer(?Panier $panier): PanierCalcule
    {
        if (null === $panier) {
            return new PanierCalcule();
        }

        $lignes = [];
        $nombreArticles = $sousTotalTtc = $totalHt = 0;
        $commandable = !$panier->isVide();

        foreach ($panier->getLignes() as $ligne) {
            $calculee = $this->calculerLigne($ligne, $panier->getLocale());
            $lignes[] = $calculee;

            if (!$calculee->disponible) {
                $commandable = false;
                continue;
            }
            $montants = $this->montantsLigne($ligne->getInstrument(), $calculee->quantite);
            $nombreArticles += $calculee->quantite;
            $sousTotalTtc += $montants['ttc'];
            $totalHt += $montants['ht'];
        }

        return new PanierCalcule(
            lignes: $lignes,
            nombreArticles: $nombreArticles,
            sousTotalTtc: $sousTotalTtc,
            totalHt: $totalHt,
            totalTva: $sousTotalTtc - $totalHt,
            fraisPortTtc: null,
            totalTtc: $sousTotalTtc,
            commandable: $commandable,
        );
    }

    /**
     * Montants d'une ligne en centimes.
     *
     * @return array{ht: int, tva: int, ttc: int}
     */
    public function montantsLigne(Instrument $instrument, int $quantite): array
    {
        $ttc = $this->prixTtc((int) $instrument->getPrixHt(), $instrument->getTauxTva()) * $quantite;
        // Régime de la marge : aucune TVA visible pour le client (elle porte sur la marge, voir LigneCommande)
        $ht = $instrument->getRegimeTva()->isMarge() ? $ttc : (int) $instrument->getPrixHt() * $quantite;

        return ['ht' => $ht, 'tva' => $ttc - $ht, 'ttc' => $ttc];
    }

    private function calculerLigne(LignePanier $ligne, string $locale): LignePanierCalculee
    {
        $instrument = $ligne->getInstrument();
        $quantiteMax = $instrument->isPublished() ? LignePanier::quantiteMaximale($instrument) : 0;
        $disponible = $quantiteMax > 0;
        $quantite = $disponible ? min($ligne->getQuantite(), $quantiteMax) : $ligne->getQuantite();
        $prixUnitaireTtc = $this->prixTtc((int) $instrument->getPrixHt(), $instrument->getTauxTva());

        $alertes = [];
        if (!$disponible) {
            $alertes[] = LignePanierCalculee::ALERTE_INDISPONIBLE;
        } elseif ($quantite < $ligne->getQuantite()) {
            $alertes[] = LignePanierCalculee::ALERTE_QUANTITE_REDUITE;
        }
        if ($ligne->isPrixModifie()) {
            $alertes[] = LignePanierCalculee::ALERTE_PRIX_MODIFIE;
        }

        return new LignePanierCalculee(
            id: (int) $ligne->getId(),
            instrument: $this->resumer($instrument, $locale),
            quantite: $quantite,
            quantiteMax: $quantiteMax,
            prixUnitaireTtc: $prixUnitaireTtc,
            prixUnitaireTtcAjout: $ligne->isPrixModifie() ? $this->prixTtc($ligne->getPrixHtAjout(), $instrument->getTauxTva()) : null,
            totalTtc: $prixUnitaireTtc * $quantite,
            disponible: $disponible,
            alertes: $alertes,
        );
    }

    private function resumer(Instrument $instrument, string $locale): InstrumentResume
    {
        $traduction = $instrument->getTraduction($locale) ?? $instrument->getTraduction('fr');
        $image = $instrument->getImages()->first() ?: null;

        return new InstrumentResume(
            id: (int) $instrument->getId(),
            reference: (string) $instrument->getReference(),
            nom: $instrument->getNom($locale) ?? (string) $instrument->getReference(),
            slug: $traduction?->getSlug(),
            image: null !== $image ? '/uploads/instruments/'.$image->getFichier() : null,
            etat: (string) $instrument->getEtat()?->value,
            anneeFabrication: $instrument->getAnneeFabrication(),
            paysOrigine: $instrument->getPaysOrigine(),
            regionOrigine: $instrument->getRegionOrigine(),
            pieceUnique: $instrument->isPieceUnique(),
        );
    }

    /**
     * Prix TTC unitaire en centimes, taux en points de base (même formule que Instrument::getPrixTtc()).
     */
    private function prixTtc(int $prixHt, int $tauxTva): int
    {
        return (int) round($prixHt * (10000 + $tauxTva) / 10000);
    }
}
