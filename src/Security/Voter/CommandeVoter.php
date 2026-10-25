<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Commande;
use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Consultation d'une commande : son client connecté, un administrateur,
 * ou quiconque présente le jeton secret du lien de suivi (paramètre « jeton », commande invité incluse).
 *
 * @extends Voter<string, Commande>
 */
final class CommandeVoter extends Voter
{
    public const VOIR = 'COMMANDE_VOIR';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VOIR === $attribute && $subject instanceof Commande;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $utilisateur = $token->getUser();
        if ($utilisateur instanceof Utilisateur) {
            if (\in_array('ROLE_ADMIN', $utilisateur->getRoles(), true)) {
                return true;
            }
            if (null !== $subject->getUtilisateur() && $subject->getUtilisateur()->getId() === $utilisateur->getId()) {
                return true;
            }
        }

        $jeton = $this->requestStack->getCurrentRequest()?->query->get('jeton');

        return \is_string($jeton) && hash_equals($subject->getJetonSuivi()->toRfc4122(), strtolower($jeton));
    }
}
