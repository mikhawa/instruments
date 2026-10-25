<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Panier;
use App\Entity\Utilisateur;
use App\Service\Panier\PanierCourant;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Accès à un panier : le client à qui il appartient, ou le visiteur qui détient son jeton.
 *
 * @extends Voter<string, Panier>
 */
final class PanierVoter extends Voter
{
    public const ACCES = 'PANIER_ACCES';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCES === $attribute && $subject instanceof Panier;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $proprietaire = $subject->getUtilisateur();
        if (null !== $proprietaire) {
            $utilisateur = $token->getUser();

            return $utilisateur instanceof Utilisateur && $utilisateur->getId() === $proprietaire->getId();
        }

        $jeton = PanierCourant::jetonDuCookie($this->requestStack->getCurrentRequest());

        return null !== $jeton && $jeton->equals($subject->getJeton());
    }
}
