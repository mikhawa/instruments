<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Authentification par cookie de session.
 * La vérification des identifiants est faite par le pare-feu (json_login) avant d'arriver ici.
 */
final class AuthController extends AbstractController
{
    private const CONTEXTE_UTILISATEUR = ['groups' => ['utilisateur:me']];

    /**
     * Atteint uniquement après une connexion réussie ; les échecs renvoient 401
     * directement depuis le pare-feu.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?Utilisateur $utilisateur): JsonResponse
    {
        if (null === $utilisateur) {
            return $this->json(['message' => 'Envoyez un JSON {"email": "…", "password": "…"} avec l\'en-tête Content-Type: application/json.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($utilisateur, context: self::CONTEXTE_UTILISATEUR);
    }

    /**
     * Utilisateur connecté, ou 401 si la session est absente ou expirée.
     */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?Utilisateur $utilisateur): JsonResponse
    {
        if (null === $utilisateur) {
            return $this->json(['message' => 'Aucun utilisateur connecté.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($utilisateur, context: self::CONTEXTE_UTILISATEUR);
    }

    /**
     * Intercepté par le pare-feu (logout) : ce code n'est jamais exécuté.
     */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('La déconnexion est gérée par le pare-feu Symfony.');
    }
}
