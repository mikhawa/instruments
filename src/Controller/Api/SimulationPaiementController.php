<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Paiement;
use App\Enum\PrestatairePaiement;
use App\Repository\PaiementRepository;
use App\Service\Paiement\Prestataires;
use App\Service\Paiement\Simulation\SimulationPrestataire;
use App\Service\Paiement\SynchronisationPaiement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Page de paiement » du faux prestataire (SimulationPrestataire), affichée par la page React
 * /paiement/simulation/{id}. N'existe pas en production (404).
 *
 *  - GET : commande, montant et statut du paiement simulé ;
 *  - POST {"issue": "paye" | "en_attente" | "echoue" | "annule" | "expire"} : fixe l'issue, applique
 *    le statut comme le ferait une notification, puis renvoie l'adresse de retour sur le site.
 */
#[AsController]
final readonly class SimulationPaiementController
{
    public function __construct(
        private Prestataires $prestataires,
        private PaiementRepository $paiementRepository,
        private SynchronisationPaiement $synchronisation,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/paiements/simulation/{id}', name: 'paiement_simulation_voir', requirements: ['id' => 'sim_[0-9a-f]{20}'], methods: ['GET'])]
    public function voir(string $id): JsonResponse
    {
        $paiement = $this->paiement($id);

        return new JsonResponse([
            'commande' => $paiement->getCommande()->getNumero(),
            'montant' => $paiement->getMontant(),
            'statut' => $paiement->getStatut()->value,
            'issues' => array_keys(SimulationPrestataire::ISSUES),
        ]);
    }

    #[Route('/api/paiements/simulation/{id}', name: 'paiement_simulation_choisir', requirements: ['id' => 'sim_[0-9a-f]{20}'], methods: ['POST'])]
    public function choisir(string $id, Request $request): JsonResponse
    {
        $paiement = $this->paiement($id);
        $issue = $request->toArray()['issue'] ?? null;
        if (!\is_string($issue) || !isset(SimulationPrestataire::ISSUES[$issue])) {
            return new JsonResponse(['detail' => 'Issue inconnue.'], 422);
        }
        if (!$paiement->getStatut()->isEnCours()) {
            return new JsonResponse(['detail' => 'Ce paiement est déjà terminé.'], 409);
        }

        $paiement->ajouterDonnees([SimulationPrestataire::ISSUE => $issue]);
        $this->entityManager->flush();
        $this->synchronisation->synchroniser($paiement);

        return new JsonResponse(['urlRetour' => $paiement->getDonnees()['urlRetour'] ?? null]);
    }

    private function paiement(string $id): Paiement
    {
        $paiement = $this->prestataires->simulationAutorisee()
            ? $this->paiementRepository->findOneBy(['prestataire' => PrestatairePaiement::Simulation, 'idExterne' => $id])
            : null;

        return $paiement ?? throw new NotFoundHttpException('Paiement introuvable.');
    }
}
