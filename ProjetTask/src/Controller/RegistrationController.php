<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private LoggerInterface $logger
    ) {}

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // Redirection si utilisateur déjà connecté  
        if ($this->getUser()) {
            $this->addFlash('info', '🔄 Vous êtes déjà connecté ! Utilisez le menu pour naviguer.');
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Vérifier si l'email existe déjà  
                $existingUser = $entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $user->getEmail()]);

                if ($existingUser) {
                    $this->addFlash(
                        'error',
                        '❌ Cette adresse email est déjà utilisée. <a href="' .
                            $this->generateUrl('app_forgot_password_request') .
                            '">Mot de passe oublié ?</a>'
                    );
                    return $this->render('registration/register.html.twig', [
                        'registrationForm' => $form->createView(),
                    ]);
                }

                // Encoder le mot de passe de manière sécurisée  
                $plainPassword = $form->get('plainPassword')->getData();
                $hashedPassword = $userPasswordHasher->hashPassword($user, $plainPassword);
                $user->setMdp($hashedPassword); // Utilisation cohérente  

                // Définir les rôles selon la sélection  
                $selectedRoles = $form->get('roles')->getData();
                $user->setRoles($selectedRoles);

                // Configuration par défaut pour nouveau compte  
                $user->setIsVerified(false); // Email non vérifié  
                $user->setDateCreation(new \DateTime()); // Horodatage de création  

                // Persister l'utilisateur en base  
                $entityManager->persist($user);
                $entityManager->flush();

                // Logger la création du compte  
                $this->logger->info('Nouveau compte créé', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'ip' => $request->getClientIp()
                ]);

                // Envoyer l'email de vérification  
                $this->sendVerificationEmail($user);

                // Message de succès détaillé  
                $this->addFlash(
                    'success',
                    '✅ <strong>Inscription réussie !</strong><br>' .
                        '📧 Un email de confirmation a été envoyé à <strong>' . $user->getEmail() . '</strong><br>' .
                        '📬 Vérifiez votre boîte de réception (et vos spams) pour activer votre compte.'
                );

                // Redirection vers login avec l'email pré-rempli  
                return $this->redirectToRoute('app_login', [
                    'email' => $user->getEmail(),
                    'registered' => 1
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'inscription', [
                    'error' => $e->getMessage(),
                    'email' => $user->getEmail() ?? 'unknown',
                    'ip' => $request->getClientIp()
                ]);

                $this->addFlash(
                    'error',
                    '❌ <strong>Erreur technique lors de l\'inscription.</strong><br>' .
                        'Veuillez réessayer dans quelques instants ou contacter le support.'
                );
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'page_title' => 'Créer votre compte SynTask'
        ]);
    }

    #[Route('/inscription-externe', name: 'app_signup', methods: ['GET', 'POST'])]
    public function signup(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // Même logique que register() mais avec des restrictions supplémentaires
        // pour les inscriptions externes (candidats, partenaires, etc.)

        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user, [
            'external_signup' => true // Option pour limiter les rôles disponibles
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Pour inscription externe, forcer le rôle EMPLOYE
                $user->setRoles(['ROLE_EMPLOYE']);
                $user->setStatut(\App\Enum\Userstatut::ACTIF); // Statut par défaut

                $plainPassword = $form->get('plainPassword')->getData();
                $user->setMdp($userPasswordHasher->hashPassword($user, $plainPassword));
                $user->setIsVerified(false);
                $user->setDateCreation(new \DateTime());

                $entityManager->persist($user);
                $entityManager->flush();

                $this->logger->info('Inscription externe créée', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'ip' => $request->getClientIp(),
                    'source' => 'external_signup'
                ]);

                $this->sendVerificationEmail($user);

                $this->addFlash(
                    'success',
                    '✅ <strong>Demande d\'inscription envoyée !</strong><br>' .
                        '📧 Un email de confirmation a été envoyé. Votre compte sera activé après validation par un administrateur.'
                );

                return $this->redirectToRoute('app_login');
            } catch (\Exception $e) {
                $this->logger->error('Erreur inscription externe', [
                    'error' => $e->getMessage(),
                    'ip' => $request->getClientIp()
                ]);
                $this->addFlash('error', '❌ Erreur lors de l\'inscription externe.');
            }
        }

        return $this->render('registration/signup.html.twig', [
            'registrationForm' => $form->createView(),
            'page_title' => 'Rejoindre SynTask'
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        try {
            $user = $this->getUser();
            $this->emailVerifier->handleEmailConfirmation($request, $user);

            // Marquer comme vérifié
            $user->setIsVerified(true);
            $entityManager->flush();

            $this->logger->info('Email vérifié avec succès', [
                'user_id' => $user->getUserIdentifier(),
                'email' => method_exists($user, 'getEmail') ? $user->getEmail() : $user->getUserIdentifier()
            ]);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->logger->warning('Échec vérification email', [
                'error' => $exception->getReason(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('error', '❌ ' . $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', '✅ <strong>Email vérifié avec succès !</strong><br>🎉 Votre compte est maintenant pleinement activé.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/renvoyer-verification', name: 'app_resend_verification')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function resendVerification(): Response
    {
        $user = $this->getUser();

        if ($user->getIsVerified()) {
            // Si l'utilisateur est actuellement verifié, ne renvoyer pas de nouveau email

            // Exemples de cas ou l'email serait renvoyé :
            // 1. L'utilisateur a oublieur son mot de passe et a réinitialisé son mot de passe
            // 2. L'utilisateur a changé son adresse email
            // 3. L'utilisateur n'a jamais reçu l'email initial (problème de livraison)

            $this->addFlash('info', '✅ Votre email est déjà vérifié !');
            return $this->redirectToRoute('app_dashboard');
        }

        try {
            $this->sendVerificationEmail($user);
            $this->addFlash('success', '📧 <strong>Email de vérification renvoyé !</strong><br>Vérifiez votre boîte de réception.');
        } catch (\Exception $e) {
            $this->addFlash('error', '❌ Erreur lors de l\'envoi. Réessayez plus tard.');
            $this->logger->error($e->getMessage());
        }

        return $this->redirectToRoute('app_dashboard');
    }

    private function sendVerificationEmail(User $user): void
    {
        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            (new TemplatedEmail())
                ->from(new Address('noreply@syntask.com', '🚀 SynTask - Gestion de Projets'))
                ->to($user->getEmail())
                ->subject('🔐 Confirmez votre inscription SynTask')
                ->htmlTemplate('registration/confirmation_email.html.twig')
                ->context([
                    'user' => $user,
                    'appName' => 'SynTask',
                    'supportEmail' => 'support@syntask.com'
                ])
        );
    }
}
