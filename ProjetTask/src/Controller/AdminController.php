<?php

namespace App\Controller;

use App\Service\UserRoleUpdater;
use App\Entity\User;
use App\Enum\Userstatut;
use App\Form\UserTypeForm;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\EmailVerifier;
use App\Security\Voter\AdminVoter;
use App\Service\AdminKanbanService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mime\Address;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private LoggerInterface $logger,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private AdminKanbanService $adminKanbanService
    ) {}

    /**
     * Dashboard principal admin
     */
    #[Route('/dashboard/all', name: 'admin_dashboard')]
    public function viewAllKanbandashboard(): Response
    {
        $this->denyAccessUnlessGranted(AdminVoter::VIEW_ALL_KANBAN);

        $statistics = $this->adminKanbanService->getGlobalStatistics();
        return $this->render('admin/dashboard.html.twig', [
            'statistics' => $statistics
        ]);
    }

    /**
     * Vue Kanban globale - ROUTE PRINCIPALE
     */
    #[Route('/kanban', name: 'admin_kanban_view')]
    public function viewAllKanban(): Response
    {
        $this->denyAccessUnlessGranted(AdminVoter::MANAGE_ALL_PROJECTS, null, AdminVoter::VIEW_ALL_KANBAN);

        $kanbanData = $this->adminKanbanService->getKanbanDataByRole($this->getUser()); // $this->getUser();

        return $this->render('admin/kanban/index.html.twig', [
            'projects' => $kanbanData['projects'],
            'tasks' => $kanbanData['tasks'],
            'users' => $kanbanData['users'],
            'taskLists' => $kanbanData['taskLists'],
            'statistics' => $kanbanData['statistics']
        ]);
    }

    /**
     * API - Déplacer une tâche (drag & drop)
     */
    #[Route('/kanban/move-task', name: 'admin_kanban_move_task', methods: ['POST'])]
    public function moveTask(Request $request): JsonResponse|Response
    {
        $this->denyAccessUnlessGranted(AdminVoter::MANAGE_ALL_PROJECTS);

        $data = json_decode($request->getContent(), true);

        $success = $this->adminKanbanService->moveTask(
            $data['taskId'],
            $data['newListId'],
            $data['newPosition']
        );

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Tâche déplacée avec succès' : 'Erreur lors du déplacement'
        ]);
    }

    /**
     * API - Mettre à jour une tâche
     */
    #[Route('/kanban/update-task/{id}', name: 'admin_kanban_update_task', methods: ['PUT'])]
    public function updateTask(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AdminVoter::MANAGE_ALL_PROJECTS);

        // Logique de mise à jour
        // ...

        return $this->json(['success' => true]);
    }

    /**
     * Gestion des utilisateurs
     */
    #[Route('/users', name: 'admin_users_list')]
    public function manageUsers(): Response
    {
        $this->denyAccessUnlessGranted(AdminVoter::MANAGE_ALL_USERS);

        // Logique de gestion des utilisateurs
        return $this->render('admin/users/index.html.twig');
    }

    /**
     * Statistiques et rapports
     */
    #[Route('/reports', name: 'admin_reports')]
    public function reports(): Response
    {
        $statistics = $this->adminKanbanService->getGlobalStatistics();

        return $this->render('admin/reports/index.html.twig', [
            'statistics' => $statistics
        ]);
    }


    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(UserRepository $userRepository, ProjectRepository $projectRepository): Response
    {
        // Statistiques globales pour le dashboard admin  
        $stats = [
            'total_users' => $userRepository->count([]),
            'active_users' => $userRepository->count(['isActive' => true]),
            'unverified_users' => $userRepository->count(['isVerified' => false]),
            'total_projects' => $projectRepository->count([]),
            'recent_users' => $userRepository->findBy([], ['dateCreation' => 'DESC'], 5),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'page_title' => 'Dashboard Administrateur'
        ]);
    }

    #[Route('', name: 'app_admin_index')]
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAllWithDetails(); // Méthode custom avec jointures  

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'current_user' => $this->getUser(),
            'page_title' => 'Gestion des Utilisateurs'
        ]);
    }

    #[Route('/user/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function newUser(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $user = new User();
        $form = $this->createForm(UserTypeForm::class, $user, [
            'can_choose_role' => true,
            'is_edit' => false
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Vérifier unicité de l'email  
                $existingUser = $entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $user->getEmail()]);

                if ($existingUser) {
                    $this->addFlash('error', '❌ Un utilisateur avec cet email existe déjà');
                    return $this->render('admin/new_user.html.twig', [
                        'form' => $form->createView(),
                        'page_title' => 'Créer un Utilisateur'
                    ]);
                }

                // Encoder le mot de passe  
                $plainPassword = $form->get('plainPassword')->getData();
                $hashedPassword = $userPasswordHasher->hashPassword($user, $plainPassword);
                $user->setMdp($hashedPassword); // Uniformisation avec setPassword()  

                // Configuration par défaut  
                $user->setDateCreation(new \DateTime());
                $user->setIsVerified(false); // Sera vérifié par email  

                // Définir les rôles depuis le formulaire  
                $selectedRoles = $form->get('roles')->getData();
                $user->setRoles($selectedRoles);

                $entityManager->persist($user);
                $entityManager->flush();

                // Logger la création  
                $this->logger->info('Utilisateur créé par admin', [
                    'admin_id' => $this->getUser()->getUserIdentifier(),
                    'created_user_id' => $user->getId(),
                    'created_user_email' => $user->getEmail(),
                    'roles' => $user->getRoles()
                ]);

                // Envoyer email de bienvenue avec lien de vérification  
                $this->sendWelcomeEmail($user, $plainPassword, $mailer);

                $this->addFlash(
                    'success',
                    '✅ <strong>Utilisateur créé avec succès !</strong><br>' .
                        '📧 Un email de bienvenue avec les instructions de connexion a été envoyé à ' . $user->getEmail()
                );

                return $this->redirectToRoute('app_admin_index');
            } catch (\Exception $e) {
                $this->logger->error('Erreur création utilisateur par admin', [
                    'admin_id' => $this->getUser()->getUserIdentifier(),
                    'error' => $e->getMessage(),
                    'email' => $user->getEmail()
                ]);

                $this->addFlash('error', '❌ Erreur lors de la création : ' . $e->getMessage());
            }
        }

        return $this->render('admin/new_user.html.twig', [
            'form' => $form->createView(),
            'page_title' => 'Créer un Nouvel Utilisateur'
        ]);
    }

    #[Route('/user/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(
        User $user,
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(UserTypeForm::class, $user, [
            'can_choose_role' => true,
            'is_edit' => true
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Changer le mot de passe seulement s'il est fourni  
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $userPasswordHasher->hashPassword($user, $plainPassword);
                    $user->setMdp($hashedPassword);
                }

                // Mettre à jour les rôles  
                $selectedRoles = $form->get('roles')->getData();
                $user->setRoles($selectedRoles);

                $entityManager->flush();

                $this->logger->info('Utilisateur modifié par admin', [
                    'admin_id' => $this->getUser()->getUserIdentifier(),
                    'modified_user_id' => $user->getId(),
                    'modified_user_email' => $user->getEmail(),
                    'password_changed' => !empty($plainPassword)
                ]);

                $this->addFlash('success', '✅ <strong>Utilisateur mis à jour avec succès !</strong>');
                return $this->redirectToRoute('app_admin_index');
            } catch (\Exception $e) {
                $this->logger->error('Erreur modification utilisateur par admin', [
                    'admin_id' => $this->getUser()->getUserIdentifier(),
                    'user_id' => $user->getId(),
                    'error' => $e->getMessage()
                ]);

                $this->addFlash('error', '❌ Erreur lors de la modification : ' . $e->getMessage());
            }
        }

        return $this->render('admin/edit_user.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'page_title' => 'Modifier ' . $user->getPrenom() . ' ' . $user->getNom()
        ]);
    }

    #[Route('/user/{id}/toggle-status', name: 'app_admin_user_toggle_status', methods: ['POST'])]
    public function toggleUserStatus(User $user, EntityManagerInterface $entityManager): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('warning', '⚠️ Vous ne pouvez pas désactiver votre propre compte !');
            return $this->redirectToRoute('app_admin_index');
        }

        $user->setIsActive(!$user->getIsActive());
        $entityManager->flush();

        $status = $user->getIsActive() ? 'activé' : 'désactivé';
        $icon = $user->getIsActive() ? '✅' : '❌';

        $this->logger->info('Statut utilisateur modifié par admin', [
            'admin_id' => $this->getUser()->getUserIdentifier(),
            'target_user_id' => $user->getId(),
            'new_status' => $user->getIsActive() ? 'active' : 'inactive'
        ]);

        $this->addFlash('success', "$icon Compte de {$user->getPrenom()} {$user->getNom()} $status");
        return $this->redirectToRoute('app_admin_index');
    }

    #[Route('/user/{id}/verify-email', name: 'app_admin_user_verify_email', methods: ['POST'])]
    public function verifyUserEmail(User $user, EntityManagerInterface $entityManager): Response
    {
        $user->setIsVerified(true);
        $entityManager->flush();

        $this->logger->info('Email utilisateur vérifié par admin', [
            'admin_id' => $this->getUser()->getRoles(),
            'target_user_id' => $user->getId(),
            'target_user_email' => $user->getEmail()
        ]);

        $this->addFlash('success', "✅ Email de {$user->getPrenom()} {$user->getNom()} marqué comme vérifié");
        return $this->redirectToRoute('app_admin_index');
    }

    #[Route('/user/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(User $user, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', '❌ Vous ne pouvez pas supprimer votre propre compte !');
            return $this->redirectToRoute('app_admin_index');
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->getPayload()->getString('_token'))) {
            $userName = $user->getPrenom() . ' ' . $user->getNom();
            $userEmail = $user->getEmail();

            $this->logger->warning('Utilisateur supprimé par admin', [
                'admin_id' => $this->getUser()->getRoles(),
                'deleted_user_id' => $user->getId(),
                'deleted_user_email' => $userEmail,
                'deleted_user_name' => $userName
            ]);

            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', "🗑️ Utilisateur $userName ($userEmail) supprimé définitivement");
        } else {
            $this->addFlash('error', '❌ Token CSRF invalide');
        }

        return $this->redirectToRoute('app_admin_index');
    }

    private function sendWelcomeEmail(User $user, string $temporaryPassword, MailerInterface $mailer): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('admin@syntask.com', '👑 SynTask Administration'))
            ->to($user->getEmail())
            ->subject('🎉 Bienvenue dans SynTask - Votre compte a été créé')
            ->htmlTemplate('admin/emails/welcome_user.html.twig')
            ->context([
                'user' => $user,
                'temporaryPassword' => $temporaryPassword,
                'loginUrl' => $this->generateUrl('app_login', [], true),
                'supportEmail' => 'support@syntask.com'
            ]);

        $mailer->send($email);
    }
}
