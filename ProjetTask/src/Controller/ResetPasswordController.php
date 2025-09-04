<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordForm;
use App\Form\ResetPasswordRequestForm;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Page de demande de réinitialisation - "Mot de passe oublié"
     */
    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer): Response
    {
        // Si utilisateur connecté, rediriger vers profil
        if ($this->getUser()) {
            $this->addFlash('info', '🔄 Vous êtes déjà connecté. Utilisez "Changer mot de passe" dans votre profil.');
            return $this->redirectToRoute('app_profile');
        }

        $form = $this->createForm(ResetPasswordRequestForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->processSendingPasswordResetEmail(
                $form->get('email')->getData(),
                $mailer,
                $request
            );
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
            'page_title' => 'Réinitialiser votre mot de passe'
        ]);
    }

    /**
     * Page de confirmation après demande
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        // Générer un token factice pour éviter l'exposition du vrai token en URL
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
            'page_title' => 'Email de réinitialisation envoyé'
        ]);
    }

    /**
     * Validation du lien de réinitialisation et changement du mot de passe
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        string $token = null
    ): Response {
        if ($token) {
            // Stocker le token en session et rediriger pour supprimer de l'URL
            $this->storeTokenInSession($token);
            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            $this->addFlash('error', '❌ Lien de réinitialisation invalide ou expiré.');
            return $this->redirectToRoute('app_forgot_password_request');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->logger->warning('Token de réinitialisation invalide', [
                'error' => $e->getReason(),
                'ip' => $request->getClientIp()
            ]);

            $this->addFlash('error', sprintf(
                '❌ %s - <a href="%s">Demander un nouveau lien</a>',
                $this->getErrorMessage($e->getReason()),
                $this->generateUrl('app_forgot_password_request')
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        // Le token est valide, afficher le formulaire de changement de mot de passe
        $form = $this->createForm(ChangePasswordForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Supprimer le token de réinitialisation de la base
            $this->resetPasswordHelper->removeResetRequest($token);

            // Encoder le nouveau mot de passe
            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->entityManager->flush();

            // Logger le changement de mot de passe
            $this->logger->info('Mot de passe réinitialisé avec succès', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $request->getClientIp()
            ]);

            // Nettoyer la session
            $this->cleanSessionAfterReset();

            $this->addFlash('success', '✅ <strong>Mot de passe mis à jour !</strong><br>Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
            'user' => $user,
            'page_title' => 'Créer un nouveau mot de passe'
        ]);
    }

    private function processSendingPasswordResetEmail(
        string $emailFormData,
        MailerInterface $mailer,
        Request $request
    ): RedirectResponse {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Ne pas révéler si l'utilisateur existe ou non
        if (!$user) {
            $this->logger->info('Tentative de réinitialisation pour email inexistant', [
                'email' => $emailFormData,
                'ip' => $request->getClientIp()
            ]);

            // Faire croire que l'email a été envoyé pour la sécurité
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->logger->warning('Erreur génération token reset', [
                'user_id' => $user->getId(),
                'error' => $e->getReason(),
                'ip' => $request->getClientIp()
            ]);

            $this->addFlash('error', '❌ Erreur technique. Veuillez réessayer dans quelques minutes.');
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@syntask.com', '🔐 SynTask Security'))
            ->to($user->getEmail())
            ->subject('🔑 Réinitialisation de votre mot de passe SynTask')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'user' => $user,
                'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
            ]);

        try {
            $mailer->send($email);

            $this->logger->info('Email de réinitialisation envoyé', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $request->getClientIp()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Échec envoi email réinitialisation', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            $this->addFlash('error', '❌ Erreur lors de l\'envoi de l\'email. Veuillez contacter le support.');
            return $this->redirectToRoute('app_forgot_password_request');
        }

        // Stocker le token objet en session pour la page de vérification email
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }

    private function getErrorMessage(string $reason): string
    {
        return match ($reason) {
            'ResetPasswordExceptionInterface::EXPIRED_TOKEN' => 'Le lien de réinitialisation a expiré',
            'ResetPasswordExceptionInterface::INVALID_TOKEN' => 'Le lien de réinitialisation est invalide',
            'ResetPasswordExceptionInterface::TOO_MANY_REQUESTS' => 'Trop de demandes. Attendez avant de redemander',
            default => 'Problème avec le lien de réinitialisation',
        };
    }
}





// #[Route('/reset-password')]
// class ResetPasswordController extends AbstractController
// {
//     use ResetPasswordControllerTrait;

//     public function __construct(
//         private ResetPasswordHelperInterface $resetPasswordHelper,
//         private EntityManagerInterface $entityManager
//     ) {
//     }

//     /**
//      * Display & process form to request a password reset.
//      */
//     #[Route('', name: 'app_forgot_password_request')]
//     public function request(Request $request, MailerInterface $mailer, TranslatorInterface $translator): Response
//     {
//         $form = $this->createForm(ResetPasswordRequestForm::class);
//         $form->handleRequest($request);

//         if ($form->isSubmitted() && $form->isValid()) {
//             /** @var string $email */
//             $email = $form->get('email')->getData();

//             return $this->processSendingPasswordResetEmail($email, $mailer, $translator
//             );
//         }

//         return $this->render('reset_password/request.html.twig', [
//             'requestForm' => $form,
//         ]);
//     }

//     /**
//      * Confirmation page after a user has requested a password reset.
//      */
//     #[Route('/check-email', name: 'app_check_email')]
//     public function checkEmail(): Response
//     {
//         // Generate a fake token if the user does not exist or someone hit this page directly.
//         // This prevents exposing whether or not a user was found with the given email address or not
//         if (null === ($resetToken = $this->getTokenObjectFromSession())) {
//             $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
//         }

//         return $this->render('reset_password/check_email.html.twig', [
//             'resetToken' => $resetToken,
//         ]);
//     }

//     /**
//      * Validates and process the reset URL that the user clicked in their email.
//      */
//     #[Route('/reset/{token}', name: 'app_reset_password')]
//     public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator, ?string $token = null): Response
//     {
//         if ($token) {
//             // We store the token in session and remove it from the URL, to avoid the URL being
//             // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
//             $this->storeTokenInSession($token);

//             return $this->redirectToRoute('app_reset_password');
//         }

//         $token = $this->getTokenFromSession();

//         if (null === $token) {
//             throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
//         }

//         try {
//             /** @var User $user */
//             $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
//         } catch (ResetPasswordExceptionInterface $e) {
//             $this->addFlash('reset_password_error', sprintf(
//                 '%s - %s',
//                 $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
//                 $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
//             ));

//             return $this->redirectToRoute('app_forgot_password_request');
//         }

//         // The token is valid; allow the user to change their password.
//         $form = $this->createForm(ChangePasswordForm::class);
//         $form->handleRequest($request);

//         if ($form->isSubmitted() && $form->isValid()) {
//             // A password reset token should be used only once, remove it.
//             $this->resetPasswordHelper->removeResetRequest($token);

//             /** @var string $plainPassword */
//             $plainPassword = $form->get('plainPassword')->getData();

//             // Encode(hash) the plain password, and set it.
//             $user->setMdp($passwordHasher->hashPassword($user, $plainPassword));
//             $this->entityManager->flush();

//             // The session is cleaned up after the password has been changed.
//             $this->cleanSessionAfterReset();

//             return $this->redirectToRoute('app_home');
//         }

//         return $this->render('reset_password/reset.html.twig', [
//             'resetForm' => $form,
//         ]);
//     }

//     private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer, TranslatorInterface $translator): RedirectResponse
//     {
//         $user = $this->entityManager->getRepository(User::class)->findOneBy([
//             'email' => $emailFormData,
//         ]);

//         // Do not reveal whether a user account was found or not.
//         if (!$user) {
//             return $this->redirectToRoute('app_check_email');
//         }

//         try {
//             $resetToken = $this->resetPasswordHelper->generateResetToken($user);
//         } catch (ResetPasswordExceptionInterface $e) {
//             // If you want to tell the user why a reset email was not sent, uncomment
//             // the lines below and change the redirect to 'app_forgot_password_request'.
//             // Caution: This may reveal if a user is registered or not.
//             //
//             // $this->addFlash('reset_password_error', sprintf(
//             //     '%s - %s',
//             //     $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_HANDLE, [], 'ResetPasswordBundle'),
//             //     $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
//             // ));

//             return $this->redirectToRoute('app_check_email');
//         }

//         $email = (new TemplatedEmail())
//             ->from(new Address('mailer@your-domain.com', 'Acme Mail Bot'))
//             ->to((string) $user->getEmail())
//             ->subject('Your password reset request')
//             ->htmlTemplate('reset_password/email.html.twig')
//             ->context([
//                 'resetToken' => $resetToken,
//             ])
//         ;

//         $mailer->send($email);

//         // Store the token object in session for retrieval in check-email route.
//         $this->setTokenObjectInSession($resetToken);

//         return $this->redirectToRoute('app_check_email');
//     }
// }
