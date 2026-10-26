<?php

declare(strict_types=1);

namespace App\Service\Paiement\Simulation;

use App\Entity\Paiement;
use App\Entity\Remboursement;
use App\Enum\PrestatairePaiement;
use App\Enum\StatutPaiement;
use App\Service\Paiement\EtatPaiement;
use App\Service\Paiement\PaiementCree;
use App\Service\Paiement\PrestatairePaiementInterface;
use App\Service\UrlSite;

/**
 * Faux prestataire du développement et des tests, utilisé quand MOLLIE_API_KEY est vide
 * (jamais en production, voir Prestataires). Sa « page de paiement » est la page React
 * /paiement/simulation/{id}, qui choisit l'issue par POST /api/paiements/simulation/{id}.
 * L'issue choisie est gardée dans les données du paiement et relue par etat(),
 * comme le ferait un vrai prestataire.
 */
final readonly class SimulationPrestataire implements PrestatairePaiementInterface
{
    public const ISSUE = 'simulation';
    /** Montant annoncé par le faux prestataire, pour tester un montant falsifié */
    public const MONTANT = 'simulationMontant';

    public const ISSUES = [
        'paye' => StatutPaiement::Paye,
        'en_attente' => StatutPaiement::EnAttente,
        'echoue' => StatutPaiement::Echoue,
        'annule' => StatutPaiement::Annule,
        'expire' => StatutPaiement::Expire,
    ];

    public function __construct(private UrlSite $urlSite)
    {
    }

    public function code(): PrestatairePaiement
    {
        return PrestatairePaiement::Simulation;
    }

    public function creer(Paiement $paiement, string $urlRetour, ?string $urlNotification): PaiementCree
    {
        $id = 'sim_'.bin2hex(random_bytes(10));
        $paiement->ajouterDonnees(['urlRetour' => $urlRetour]);

        return new PaiementCree($id, $this->urlSite->absolue('/paiement/simulation/'.$id));
    }

    public function etat(Paiement $paiement): EtatPaiement
    {
        $donnees = $paiement->getDonnees();
        $statut = self::ISSUES[$donnees[self::ISSUE] ?? ''] ?? StatutPaiement::Ouvert;
        $montant = (int) ($donnees[self::MONTANT] ?? $paiement->getMontant());
        $rembourse = $paiement->getMontantRembourse();
        if (StatutPaiement::Paye === $statut && $rembourse > 0) {
            $statut = $rembourse >= $montant ? StatutPaiement::Rembourse : StatutPaiement::PartiellementRembourse;
        }

        return new EtatPaiement(
            statut: $statut,
            montant: $montant,
            devise: $paiement->getDevise(),
            moyen: $statut->isEncaisse() ? 'simulation' : null,
            montantRembourse: $rembourse,
        );
    }

    public function rembourser(Remboursement $remboursement): array
    {
        return ['sim_re_'.bin2hex(random_bytes(8)), true];
    }
}
