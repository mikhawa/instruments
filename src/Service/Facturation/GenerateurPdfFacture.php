<?php

declare(strict_types=1);

namespace App\Service\Facturation;

use App\Entity\Facture;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * PDF d'une facture ou d'une note de crédit (dompdf : 100 % PHP, rien à installer sur le VPS).
 * Produit à la demande à partir des données figées de la facture : le résultat est toujours le même.
 */
final readonly class GenerateurPdfFacture
{
    public function __construct(private Environment $twig)
    {
    }

    public function pdf(Facture $facture): string
    {
        $options = (new Options())
            ->setDefaultFont('DejaVu Sans')
            // Aucune ressource externe : le modèle est autonome
            ->setIsRemoteEnabled(false)
            ->setIsPhpEnabled(false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render('factures/facture.html.twig', ['facture' => $facture]), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function nomFichier(Facture $facture): string
    {
        return $facture->getNumero().'.pdf';
    }
}
