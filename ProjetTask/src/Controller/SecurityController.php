<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Rediriger si déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('employe_dashboard');
        }
        // Récupérer les erreurs de connexion
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    #[Route(path: '/register', name: 'app_register')]
    public function register(): Response
    {
        // Logique d'enregistrement de l'utilisateur
        // Pour l'exemple, on redirige vers la page de connexion
        $this->addFlash('success', 'Inscription réussie ! Veuillez vous connecter.');
        return $this->redirectToRoute('app_login');
    }
    #[Route(path: '/forgot-password', name: 'app_forgot_password_request')]
    public function forgotPassword(): Response
    {
        // Logique de réinitialisation du mot de passe
        // Pour l'exemple, on redirige vers la page de connexion
        $this->addFlash('info', 'Un email de réinitialisation a été envoyé si l\'email existe.');
        return $this->redirectToRoute('app_login');
    }
}
