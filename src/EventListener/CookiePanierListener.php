<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\Panier\PanierCourant;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Pose ou efface le cookie « panier » demandé pendant la requête (voir PanierCourant).
 * Cookie fonctionnel (pas de consentement) : HttpOnly, SameSite=Lax, 30 jours.
 */
#[AsEventListener]
final class CookiePanierListener
{
    private const DUREE = '+30 days';

    public function __invoke(ResponseEvent $event): void
    {
        $requete = $event->getRequest();
        if (!$event->isMainRequest() || !$requete->attributes->has(PanierCourant::ATTRIBUT_COOKIE)) {
            return;
        }

        $jeton = $requete->attributes->get(PanierCourant::ATTRIBUT_COOKIE);
        $cookies = $event->getResponse()->headers;

        if (false === $jeton) {
            $cookies->clearCookie(PanierCourant::COOKIE, '/', null, $requete->isSecure(), true, Cookie::SAMESITE_LAX);

            return;
        }

        $cookies->setCookie(Cookie::create(PanierCourant::COOKIE)
            ->withValue($jeton)
            ->withExpires(new \DateTimeImmutable(self::DUREE))
            ->withPath('/')
            ->withSecure($requete->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX));
    }
}
