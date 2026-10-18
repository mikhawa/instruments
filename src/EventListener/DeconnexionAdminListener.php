<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Après déconnexion du back-office, retour à l'accueil du site public.
 *
 * Redirection faite ici plutôt qu'avec « logout.target » : HttpUtils remplace une URL
 * vers un autre hôte par « / » (anti-redirection ouverte), ce qui casserait le dev
 * où le site React tourne sur le serveur Vite (FRONT_URL=http://localhost:5173).
 */
#[AsEventListener(event: LogoutEvent::class, dispatcher: 'security.event_dispatcher.admin')]
final class DeconnexionAdminListener
{
    public function __construct(
        #[Autowire(env: 'FRONT_URL')]
        private readonly string $urlSite,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new RedirectResponse($this->urlSite));
    }
}
