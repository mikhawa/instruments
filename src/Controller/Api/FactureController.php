<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Facturation\GenerateurPdfFacture;
use App\State\Commande\CommandeDuClient;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/commandes/{numero}/factures/{facture}.pdf[?jeton=…] : facture ou note de crédit en PDF,
 * pour qui peut voir la commande (son client, un administrateur, ou le lien de suivi). 404 sinon.
 */
#[AsController]
final readonly class FactureController
{
    public function __construct(
        private CommandeDuClient $commandeDuClient,
        private GenerateurPdfFacture $generateur,
    ) {
    }

    #[Route('/api/commandes/{numero}/factures/{facture}.pdf', name: 'facture_pdf', requirements: ['numero' => 'CMD-\d{4}-\d{6}', 'facture' => '(F|NC)\d{4}-\d{6}'], methods: ['GET'])]
    public function __invoke(string $numero, string $facture): Response
    {
        $commande = $this->commandeDuClient->trouver(['numero' => $numero]);
        foreach ($commande->getFactures() as $document) {
            if ($document->getNumero() === $facture) {
                return new Response($this->generateur->pdf($document), Response::HTTP_OK, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $this->generateur->nomFichier($document)),
                    // Document personnel : jamais en cache partagé
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        throw new NotFoundHttpException('Facture introuvable.');
    }
}
