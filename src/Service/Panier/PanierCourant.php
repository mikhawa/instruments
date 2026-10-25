<?php

declare(strict_types=1);

namespace App\Service\Panier;

use App\Entity\Panier;
use App\Entity\Utilisateur;
use App\Repository\PanierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Retrouve le panier de la requête : celui du client connecté, sinon celui du visiteur
 * désigné par le cookie « panier ». Le cookie lui-même est posé ou effacé sur la réponse
 * par CookiePanierListener, d'après l'attribut de requête ATTRIBUT_COOKIE.
 */
final readonly class PanierCourant
{
    public const COOKIE = 'panier';
    /** Valeur : jeton à poser (string) ou false pour effacer le cookie */
    public const ATTRIBUT_COOKIE = '_panier_cookie';

    public function __construct(
        private RequestStack $requestStack,
        private Security $security,
        private PanierRepository $panierRepository,
        private FusionPanier $fusionPanier,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Panier existant, ou null : aucune écriture en base ni cookie pour un simple visiteur.
     */
    public function trouver(): ?Panier
    {
        $utilisateur = $this->utilisateur();
        if (null !== $utilisateur) {
            // Connexion par un autre chemin que l'API (back-office) : le cookie visiteur est encore là
            return $this->rattacherPanierVisiteur($utilisateur)
                ?? $this->panierRepository->trouverPanierUtilisateur($utilisateur);
        }

        return $this->panierVisiteur();
    }

    /**
     * Panier existant ou nouveau panier (persisté, à enregistrer par l'appelant).
     */
    public function obtenirOuCreer(): Panier
    {
        $panier = $this->trouver();
        if (null !== $panier) {
            return $panier;
        }

        $requete = $this->requestStack->getCurrentRequest();
        $panier = new Panier($requete?->getLocale() ?? 'fr');
        $panier->setUtilisateur($this->utilisateur());
        $this->entityManager->persist($panier);

        if (null === $panier->getUtilisateur()) {
            $requete?->attributes->set(self::ATTRIBUT_COOKIE, $panier->getJeton()->toRfc4122());
        }

        return $panier;
    }

    /**
     * Fusionne le panier du cookie dans celui du client puis demande l'effacement du cookie.
     * Renvoie le panier du client, ou null s'il n'y avait pas de cookie (rien à faire).
     */
    public function rattacherPanierVisiteur(Utilisateur $utilisateur): ?Panier
    {
        $requete = $this->requestStack->getCurrentRequest();
        if (null === $requete || !$requete->cookies->has(self::COOKIE)) {
            return null;
        }

        $requete->attributes->set(self::ATTRIBUT_COOKIE, false);
        $panierVisiteur = $this->panierVisiteur();

        return null !== $panierVisiteur
            ? $this->fusionPanier->fusionner($panierVisiteur, $utilisateur)
            : $this->panierRepository->trouverPanierUtilisateur($utilisateur);
    }

    /**
     * Jeton lu dans le cookie, ou null s'il est absent ou mal formé.
     */
    public static function jetonDuCookie(?Request $requete): ?Uuid
    {
        $valeur = $requete?->cookies->get(self::COOKIE);

        return \is_string($valeur) && Uuid::isValid($valeur) ? Uuid::fromString($valeur) : null;
    }

    private function panierVisiteur(): ?Panier
    {
        $jeton = self::jetonDuCookie($this->requestStack->getCurrentRequest());

        return null !== $jeton ? $this->panierRepository->trouverPanierVisiteur($jeton) : null;
    }

    private function utilisateur(): ?Utilisateur
    {
        $utilisateur = $this->security->getUser();

        return $utilisateur instanceof Utilisateur ? $utilisateur : null;
    }
}
