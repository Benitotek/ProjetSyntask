<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class SecurityController extends AbstractController
{
    private $csrf;

    public function __construct(CsrfTokenManagerInterface $csrf)
    {
        $this->csrf = $csrf;
    }

    /**
     * Page d'accueil de l'application
     */

    /**
     * Génère un token CSRF pour les requêtes AJAX
     */
    #[Route('/generate-csrf-token', name: 'app_generate_csrf_token', methods: ['GET'])]
    public function generateCsrfToken(Request $request): JsonResponse
    {
        $id = $request->query->get('id');

        if (!$id) {
            return new JsonResponse(['error' => 'ID manquant'], 400);
        }

        $token = $this->csrf->getToken($id)->getValue();

        return new JsonResponse(['token' => $token]);
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername() ?? '';

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'referer' => $request->headers->get('referer')
        ]);
    }
    // public function login(\Symfony\Component\HttpFoundation\Request $request, AuthenticationUtils $authenticationUtils): Response
    // {
    // Si l'utilisateur est déjà connecté, NE PAS rediriger automatiquement
    // Cette redirection peut causer des problèmes
    // Laissez l'utilisateur décider s'il veut naviguer ailleurs

    // Rediriger si déjà connecté
    // if ($this->getUser()) {
    //     return $this->redirectToRoute('app_home');
    // }
    // Récupérer les erreurs de connexion
    //     $error = $authenticationUtils->getLastAuthenticationError();
    //     $lastUsername = $authenticationUtils->getLastUsername();

    //     return $this->render('security/login.html.twig', [
    //         'last_username' => $lastUsername,
    //         'error' => $error,
    //         'referer' => $request->headers->get('referer')
    //     ]);
    // }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // pas mettre d'exeption ici, Symfony gère la déconnexion
        // throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
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
