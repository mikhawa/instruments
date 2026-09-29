<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Remplace la redirection par défaut de la déconnexion par une réponse 204,
 * adaptée à un appel fetch() depuis React.
 */
#[AsEventListener(event: LogoutEvent::class, dispatcher: 'security.event_dispatcher.main')]
final class DeconnexionListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new Response(status: Response::HTTP_NO_CONTENT));
    }
}
