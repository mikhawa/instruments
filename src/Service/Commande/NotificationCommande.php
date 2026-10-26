<?php

declare(strict_types=1);

namespace App\Service\Commande;

use App\Entity\Commande;
use App\Entity\Paiement;
use App\Service\Facturation\GenerateurPdfFacture;
use App\Service\Paiement\Virement\CoordonneesVirement;
use App\Service\UrlSite;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * E-mails envoyés au client (asynchrones : Messenger, transport « async »).
 * Les modèles sont dans templates/emails/commande/.
 */
final readonly class NotificationCommande
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlSite $urlSite,
        private CoordonneesVirement $coordonneesVirement,
        private GenerateurPdfFacture $generateurPdf,
        #[Autowire(env: 'MAILER_EXPEDITEUR')]
        private string $expediteur,
    ) {
    }

    public function commandeEnregistree(Commande $commande): void
    {
        $this->envoyer($commande, 'enregistree', \sprintf('Votre commande %s est enregistrée', $commande->getNumero()));
    }

    /**
     * Confirmation du paiement, avec la facture en pièce jointe.
     */
    public function commandePayee(Commande $commande): void
    {
        $pieces = [];
        foreach ($commande->getFactures() as $facture) {
            if (!$facture->isNoteCredit()) {
                $pieces[$this->generateurPdf->nomFichier($facture)] = $this->generateurPdf->pdf($facture);
            }
        }
        $this->envoyer($commande, 'payee', \sprintf('Paiement reçu pour votre commande %s', $commande->getNumero()), pieces: $pieces);
    }

    /**
     * Paiement arrivé trop tard (instruments plus disponibles) : il est remboursé.
     */
    public function paiementRembourse(Commande $commande, int $montant): void
    {
        $this->envoyer($commande, 'remboursee', \sprintf('Commande %s : remboursement de votre paiement', $commande->getNumero()), ['montant' => $montant]);
    }

    /**
     * Remboursement (total ou partiel) accordé par le magasin, sans annulation de la commande.
     */
    public function remboursementAccorde(Commande $commande, int $montant, string $motif): void
    {
        $this->envoyer($commande, 'remboursement', \sprintf('Commande %s : remboursement de %s €', $commande->getNumero(), number_format($montant / 100, 2, ',', ' ')), ['montant' => $montant, 'motif' => $motif]);
    }

    public function instructionsVirement(Paiement $paiement): void
    {
        $commande = $paiement->getCommande();
        $this->envoyer($commande, 'virement', \sprintf('Commande %s : informations pour votre virement', $commande->getNumero()), [
            'paiement' => $paiement,
            'coordonnees' => $this->coordonneesVirement,
        ]);
    }

    public function commandeExpediee(Commande $commande): void
    {
        $this->envoyer($commande, 'expediee', \sprintf('Votre commande %s est en route', $commande->getNumero()));
    }

    public function commandeAnnulee(Commande $commande): void
    {
        $this->envoyer($commande, 'annulee', \sprintf('Votre commande %s est annulée', $commande->getNumero()));
    }

    /**
     * Lien « suivre ma commande », valable sans compte grâce au jeton secret.
     */
    public function urlSuivi(Commande $commande): string
    {
        return $this->urlSite->absolue('/commande/suivi/'.$commande->getNumero(), ['jeton' => $commande->getJetonSuivi()->toRfc4122()]);
    }

    /**
     * @param array<string, mixed>  $contexte
     * @param array<string, string> $pieces   nom du fichier PDF => contenu
     */
    private function envoyer(Commande $commande, string $modele, string $sujet, array $contexte = [], array $pieces = []): void
    {
        $email = new TemplatedEmail();
        foreach ($pieces as $nom => $contenu) {
            $email->attach($contenu, $nom, 'application/pdf');
        }
        $this->mailer->send($email
            ->from(Address::create($this->expediteur))
            ->to(new Address((string) $commande->getEmail(), $commande->getNomClient()))
            ->subject($sujet)
            ->htmlTemplate(\sprintf('emails/commande/%s.html.twig', $modele))
            ->context([
                'commande' => $commande,
                'urlSuivi' => $this->urlSuivi($commande),
                'urlSite' => $this->urlSite->absolue('/'),
                ...$contexte,
            ]));
    }
}
