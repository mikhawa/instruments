<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Utilisateur;
use App\Service\Panier\PanierCourant;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Connexion réussie (site ou back-office) : le panier du visiteur rejoint celui du client.
 */
#[AsEventListener]
final readonly class FusionPanierALaConnexionListener
{
    public function __construct(private PanierCourant $panierCourant)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $utilisateur = $event->getUser();
        if ($utilisateur instanceof Utilisateur) {
            $this->panierCourant->rattacherPanierVisiteur($utilisateur);
        }
    }
}
