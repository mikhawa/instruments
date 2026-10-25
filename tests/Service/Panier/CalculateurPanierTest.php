<?php

declare(strict_types=1);

namespace App\Tests\Service\Panier;

use App\ApiResource\Panier\LignePanierCalculee;
use App\Entity\Instrument;
use App\Entity\InstrumentTraduction;
use App\Entity\LignePanier;
use App\Entity\Panier;
use App\Enum\EtatInstrument;
use App\Service\Panier\CalculateurPanier;
use PHPUnit\Framework\TestCase;

/**
 * Montants du panier : arrondis, TVA, lignes indisponibles, alertes (aucune base nécessaire).
 */
final class CalculateurPanierTest extends TestCase
{
    private CalculateurPanier $calculateur;

    protected function setUp(): void
    {
        $this->calculateur = new CalculateurPanier();
    }

    public function testPanierAbsentOuVide(): void
    {
        foreach ([null, new Panier()] as $panier) {
            $calcule = $this->calculateur->calculer($panier);

            self::assertSame([], $calcule->lignes);
            self::assertSame(0, $calcule->totalTtc);
            self::assertSame(0, $calcule->nombreArticles);
            self::assertFalse($calcule->commandable);
        }
    }

    public function testArrondiParPrixUnitaireEtTvaEgaleTtcMoinsHt(): void
    {
        // 3,33 € HT à 21 % = 4,0293 € → 4,03 € TTC l'unité, × 3 = 12,09 €
        $panier = new Panier();
        new LignePanier($panier, $this->instrument(prixHt: 333, stock: 5), 3);

        $calcule = $this->calculateur->calculer($panier);

        self::assertSame(403, $calcule->lignes[0]->prixUnitaireTtc);
        self::assertSame(1209, $calcule->sousTotalTtc);
        self::assertSame(999, $calcule->totalHt);
        self::assertSame(210, $calcule->totalTva);
        self::assertSame(1209, $calcule->totalTtc);
        self::assertSame(3, $calcule->nombreArticles);
        self::assertTrue($calcule->commandable);
    }

    public function testPlusieursTauxDeTva(): void
    {
        $panier = new Panier();
        new LignePanier($panier, $this->instrument(prixHt: 10000, stock: 2), 2);
        new LignePanier($panier, $this->instrument(prixHt: 5000, stock: 1, tauxTva: 600), 1);

        $calcule = $this->calculateur->calculer($panier);

        // 2 × 121,00 + 53,00
        self::assertSame(29500, $calcule->sousTotalTtc);
        self::assertSame(25000, $calcule->totalHt);
        self::assertSame(4500, $calcule->totalTva);
    }

    public function testLigneIndisponibleHorsTotauxEtPanierNonCommandable(): void
    {
        $panier = new Panier();
        new LignePanier($panier, $this->instrument(prixHt: 10000, stock: 1), 1);
        $epuise = $this->instrument(prixHt: 20000, stock: 1);
        new LignePanier($panier, $epuise, 1);
        $epuise->getStock()->reserver(1);

        $calcule = $this->calculateur->calculer($panier);

        self::assertSame(12100, $calcule->totalTtc);
        self::assertSame(1, $calcule->nombreArticles);
        self::assertFalse($calcule->commandable);
        self::assertFalse($calcule->lignes[1]->disponible);
        self::assertSame(0, $calcule->lignes[1]->quantiteMax);
        self::assertSame([LignePanierCalculee::ALERTE_INDISPONIBLE], $calcule->lignes[1]->alertes);
    }

    public function testInstrumentDepublieIndisponible(): void
    {
        $panier = new Panier();
        $instrument = $this->instrument(prixHt: 10000, stock: 3);
        new LignePanier($panier, $instrument, 1);
        $instrument->setPublished(false);

        $calcule = $this->calculateur->calculer($panier);

        self::assertFalse($calcule->lignes[0]->disponible);
        self::assertSame(0, $calcule->totalTtc);
    }

    public function testQuantiteRameneeAuStock(): void
    {
        $panier = new Panier();
        $instrument = $this->instrument(prixHt: 10000, stock: 3);
        new LignePanier($panier, $instrument, 3);
        $instrument->getStock()->reserver(2);

        $ligne = $this->calculateur->calculer($panier)->lignes[0];

        self::assertSame(1, $ligne->quantite);
        self::assertSame(12100, $ligne->totalTtc);
        self::assertSame([LignePanierCalculee::ALERTE_QUANTITE_REDUITE], $ligne->alertes);
    }

    public function testPrixModifieDepuisLAjout(): void
    {
        $panier = new Panier();
        $instrument = $this->instrument(prixHt: 10000, stock: 1);
        new LignePanier($panier, $instrument, 1);
        $instrument->setPrixHt(9000);

        $ligne = $this->calculateur->calculer($panier)->lignes[0];

        self::assertSame(10890, $ligne->prixUnitaireTtc);
        self::assertSame(12100, $ligne->prixUnitaireTtcAjout);
        self::assertSame([LignePanierCalculee::ALERTE_PRIX_MODIFIE], $ligne->alertes);
    }

    public function testPieceUniquePlafonneeAUn(): void
    {
        $instrument = $this->instrument(prixHt: 10000, stock: 1, pieceUnique: true);

        self::assertSame(1, LignePanier::quantiteMaximale($instrument));
        self::assertSame(LignePanier::QUANTITE_MAX, LignePanier::quantiteMaximale($this->instrument(prixHt: 100, stock: 50)));
    }

    private function instrument(int $prixHt, int $stock, int $tauxTva = 2100, bool $pieceUnique = false): Instrument
    {
        $instrument = (new Instrument())
            ->setReference('TST-CALC')
            ->setEtat(EtatInstrument::Neuf)
            ->setPieceUnique($pieceUnique)
            ->setPrixHt($prixHt)
            ->setTauxTva($tauxTva)
            ->setPublished(true)
            ->addTraduction((new InstrumentTraduction())->setLocale('fr')->setNom('Instrument de test')->setSlug('instrument-de-test'));
        $instrument->getStock()->ajouter($stock);

        return $instrument;
    }
}
