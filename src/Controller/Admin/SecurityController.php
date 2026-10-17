<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Connexion au back-office (pare-feu « admin », form_login).
 */
final class SecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin');
        }

        return $this->render('@EasyAdmin/page/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'page_title' => 'Instruments — Administration',
            'csrf_token_intention' => 'authenticate',
            'action' => $this->generateUrl('admin_login'),
            'target_path' => $this->generateUrl('admin'),
            'username_parameter' => 'email',
            'password_parameter' => 'password',
            'username_label' => 'Adresse e-mail',
            'password_label' => 'Mot de passe',
            'sign_in_label' => 'Se connecter',
        ]);
    }

    /**
     * Intercepté par le pare-feu (logout) : ce code n'est jamais exécuté.
     */
    #[Route('/admin/logout', name: 'admin_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('La déconnexion est gérée par le pare-feu Symfony.');
    }
}
