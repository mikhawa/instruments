<?php

declare(strict_types=1);

namespace App\Service\Commande;

use App\Entity\Commande;
use App\Entity\Paiement;
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
        #[Autowire(env: 'MAILER_EXPEDITEUR')]
        private string $expediteur,
    ) {
    }

    public function commandeEnregistree(Commande $commande): void
    {
        $this->envoyer($commande, 'enregistree', \sprintf('Votre commande %s est enregistrée', $commande->getNumero()));
    }

    public function commandePayee(Commande $commande): void
    {
        $this->envoyer($commande, 'payee', \sprintf('Paiement reçu pour votre commande %s', $commande->getNumero()));
    }

    /**
     * Paiement arrivé trop tard (instruments plus disponibles) : il est remboursé.
     */
    public function paiementRembourse(Commande $commande, int $montant): void
    {
        $this->envoyer($commande, 'remboursee', \sprintf('Commande %s : remboursement de votre paiement', $commande->getNumero()), ['montant' => $montant]);
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
     * @param array<string, mixed> $contexte
     */
    private function envoyer(Commande $commande, string $modele, string $sujet, array $contexte = []): void
    {
        $this->mailer->send((new TemplatedEmail())
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
