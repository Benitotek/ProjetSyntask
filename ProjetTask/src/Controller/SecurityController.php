<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;

class SecurityController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        // Si utilisateur déjà connecté, rediriger selon son rôle
        if ($this->getUser()) {
            return $this->redirectToUserDashboard();
        }

        // Récupérer l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();

        // Récupérer le dernier nom d'utilisateur saisi
        $lastUsername = $authenticationUtils->getLastUsername();

        // Email pré-rempli depuis l'inscription
        $emailFromRegistration = $request->query->get('email');
        $isFromRegistration = $request->query->getBoolean('registered');

        // Logger les tentatives de connexion échouées
        if ($error) {
            $this->logger->warning('Échec de connexion', [
                'username' => $lastUsername,
                'error' => $error->getMessageKey(),
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);

            // Messages d'erreur personnalisés en français
            $errorMessage = $this->getCustomErrorMessage($error->getMessageKey());
            $this->addFlash('error', $errorMessage);
        }

        // Message de bienvenue pour nouveau compte
        if ($isFromRegistration) {
            $this->addFlash(
                'info',
                '🎉 <strong>Inscription terminée !</strong><br>' .
                    '📧 Vérifiez votre email pour activer votre compte, puis connectez-vous.'
            );
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $emailFromRegistration ?: $lastUsername,
            'error' => $error,
            'is_from_registration' => $isFromRegistration,
            'page_title' => 'Connexion à SynTask'
        ]);
    }
    //   public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    // {
    //     $lastUsername = $authenticationUtils->getLastUsername();
    //     $error = $authenticationUtils->getLastAuthenticationError();
    //     $isFromRegistration = $request->query->get('is_from_registration') === 'true';
    //     $emailFromRegistration = $request->query->get('email_from_registration');
    //     $emailFromRegistration = $emailFromRegistration !== null ? $emailFromRegistration : '';

    //     // Si utilisateur déjà connecté, rediriger selon son rôle
    //     if ($this->getUser()) {
    //         return $this->redirectToUserDashboard();
    //     }

    //     // Récupérer l'erreur de connexion s'il y en a une
    //     if ($error) {
    //         $this->addFlash(
    //             'error',
    //             match ($error->getMessageKey()) {
    //                 'Bad credentials' => '🚫 Identifiants incorrects. Veuillez essayer encore.',
    //                 'Account is not verified' => '📧 Compte non vérifié. Veuillez activer votre compte.',
    //                 'Account is disabled' => '🚫 Compte désactivé. Contactez l\'administrateur.',
    //                 'Account is locked' => '🚫 Compte verrouillé. Réinitialisez votre mot de passe.',
    //                 'Account is expired' => '🚫 Compte expiré. Contactez l\'administrateur.',
    //                 'Credentials have expired' => '🚫 Vos identifiants ont expiré. Réinitialisez votre mot de passe.',
    //                 default => '🚫 Erreur de connexion inconnue. Veuillez réessayer plus tard.'
    //             },
    //             ['username' => $lastUsername, 'ip' => $request->getClientIp(), 'user_agent' => $request->headers->get('User-Agent')]

    //         );
    //     }

    //     return $this->render('security/login.html.twig', [
    //         'last_username' => $lastUsername,
    //         'email_from_registration' => $emailFromRegistration
    //     ]);
    //     // Message de bienvenue pour nouveau compte
    //     if ($isFromRegistration) {
    //         $this->addFlash(
    //             'success',
    //             '🎉 Bienvenue sur SynTask ! <br> Votre compte a bien été créé. Veuillez vous connecter pour accéder à votre tableau de bord.'
    //         );
    //     }

    //     // Rediriger vers le tableau de bord si l'utilisateur est connecté
    //     if ($this->getUser()) {
    //         return $this->redirectToRoute('app_user_dashboard');
    //     }

    //     return $this->render('security/login.html.twig', [
    //         'last_username' => $lastUsername,
    //         'error' => $error,
    //         'is_from_registration' => $isFromRegistration,
    //         'page_title' => 'Connexion à SynTask'
    //     ]);
    //     throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    // }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Cette méthode peut rester vide - elle sera interceptée par la clé de logout dans security.yaml
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/acces-refuse', name: 'app_access_denied')]
    public function accessDenied(): Response
    {
        return $this->render('security/access_denied.html.twig', [
            'page_title' => 'Accès Refusé',
            'user' => $this->getUser()
        ]);
    }

    #[Route(path: '/compte-non-verifie', name: 'app_unverified')]
    public function unverified(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            return $this->redirectToUserDashboard();
        }

        return $this->render('security/unverified.html.twig', [
            'user' => $user,
            'page_title' => 'Compte Non Vérifié'
        ]);
    }

    private function redirectToUserDashboard(): Response
    {
        $user = $this->getUser();
        $roles = $user->getRoles();

        // Redirection intelligente selon les rôles
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_SUPER_ADMIN', $roles)) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if (in_array('ROLE_DIRECTEUR', $roles)) {
            return $this->redirectToRoute('app_directeur_dashboard');
        }

        if (in_array('ROLE_CHEF_PROJET', $roles)) {
            return $this->redirectToRoute('app_chef_projet_dashboard');
        }

        // Par défaut, dashboard employé
        return $this->redirectToRoute('app_employe_dashboard');
    }

    private function getCustomErrorMessage(string $errorKey): string
    {
        return match ($errorKey) {
            'Invalid credentials.' => '❌ <strong>Identifiants incorrects</strong><br>Vérifiez votre email et mot de passe. <a href="' .
                $this->generateUrl('app_forgot_password_request') . '">Mot de passe oublié ?</a>',
            'Account is not verified.' => '📧 <strong>Compte non vérifié</strong><br>Vérifiez votre email pour activer votre compte. <a href="' .
                $this->generateUrl('app_resend_verification') . '">Renvoyer l\'email de vérification</a>',
            'Account is disabled.' => '🚫 <strong>Compte désactivé</strong><br>Contactez un administrateur pour réactiver votre compte.',
            'Account is locked.' => '🔒 <strong>Compte verrouillé</strong><br>Trop de tentatives échouées. Réessayez plus tard ou contactez le support.',
            'Username could not be found.' => '❓ <strong>Utilisateur introuvable</strong><br>Aucun compte trouvé avec cet email. <a href="' .
                $this->generateUrl('app_register') . '">Créer un compte</a>',
            default => '❌ <strong>Erreur de connexion</strong><br>Problème technique. Veuillez réessayer.'
        };
    }
}











    // {
    // private $csrf;

    // public function __construct(CsrfTokenManagerInterface $csrf)
    // {
    //     $this->csrf = $csrf;
    // }

    // /**
    //  * Page d'accueil de l'application
    //  */

    // /**
    //  * Génère un token CSRF pour les requêtes AJAX
    //  */
    // #[Route('/generate-csrf-token', name: 'app_generate_csrf_token', methods: ['GET'])]
    // public function generateCsrfToken(Request $request): JsonResponse
    // {
    //     $id = $request->query->get('id');

    //     if (!$id) {
    //         return new JsonResponse(['error' => 'ID manquant'], 400);
    //     }

    //     $token = $this->csrf->getToken($id)->getValue();

    //     return new JsonResponse(['token' => $token]);
    // }

    // #[Route(path: '/login', name: 'app_login')]
    // public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    // {
    //     $error = $authenticationUtils->getLastAuthenticationError();
    //     $lastUsername = $authenticationUtils->getLastUsername() ?? '';

    //     return $this->render('security/login.html.twig', [
    //         'last_username' => $lastUsername,
    //         'error' => $error,
    //         'referer' => $request->headers->get('referer')
    //     ]);
    // }
//     #[Route(path: '/logout', name: 'app_logout')]
//     public function logout(): void
//     {
//         // pas mettre d'exeption ici, Symfony gère la déconnexion
//         // throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
//     }
//     // Page d'enregistrement
//     #[Route(path: '/register', name: 'app_register')]
//     public function register(): Response
//     {
//         // Logique d'enregistrement de l'utilisateur
//         // Pour l'exemple, on redirige vers la page de connexion
//         $this->addFlash('success', 'Inscription réussie ! Veuillez vous connecter.');
//         return $this->redirectToRoute('app_login');
//     }
//     // Réinitialisation du mot de passe
//     #[Route(path: '/forgot-password', name: 'app_forgot_password_request')]
//     public function forgotPassword(Request $request, UserRepository $userRepository, MailerInterface $mailer): Response
//     {
//         if ($request->isMethod('POST')) {
//             $email = $request->request->get('email');
//             $user = $userRepository->findOneBy(['email' => $email]);

//             if ($user) {
//                 // Envoie l'e-mail ici avec un lien contenant un token
//                 // Exemple fictif — à implémenter correctement avec un générateur de token et un système sécurisé
//                 // $resetToken = ...;
//                 // $mailer->send(...);
//             }

//             $this->addFlash('info', 'Un email de réinitialisation a été envoyé si l\'email existe.');
//             return $this->redirectToRoute('app_login');
//         }

//         return $this->render('security/forgot_password.html.twig');
//     }
// }
