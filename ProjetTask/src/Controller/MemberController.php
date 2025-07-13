<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/project')]
class MemberController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ActivityLogger $activityLogger;
    private NotificationService $notificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
        NotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->activityLogger = $activityLogger;
        $this->notificationService = $notificationService;
    }

    #[Route('/{id}/members', name: 'app_project_members')]
    #[IsGranted('VIEW', 'project')]
    public function members(Project $project): Response
    {
        // Séparer les membres par rôle
        $chefsProjets = [];
        $employes = [];

        foreach ($project->getMembres() as $membre) {
            if ($membre->hasRole('ROLE_CHEF_PROJET')) {
                $chefsProjets[] = $membre;
            } else {
                $employes[] = $membre;
            }
        }

        return $this->render('project/members.html.twig', [
            'project' => $project,
            'chefsProjets' => $chefsProjets,
            'employes' => $employes,
        ]);
    }


    #[Route('/{id}/members/add', name: 'app_project_members_add', methods: ['POST'])]
    #[IsGranted('EDIT', 'project')]
    public function addMember(Project $project, Request $request, UserRepository $userRepository): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('add_member' . $project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        $userId = $request->request->get('user_id');
        if (!$userId) {
            $this->addFlash('error', 'Veuillez sélectionner un utilisateur.');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        $user = $userRepository->find($userId);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        // Vérifier si l'utilisateur est déjà membre
        if ($project->getMembres()->contains($user)) {
            $this->addFlash('info', 'Cet utilisateur est déjà membre du projet.');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        // Ajouter l'utilisateur comme membre
        $project->addMembre($user);
        $this->entityManager->flush();

        // Créer une notification pour l'utilisateur
        $this->notificationService->createNotification(
            $user,
            'Ajout à un projet',
            'Vous avez été ajouté au projet "' . $project->getTitre() . '".',
            '/project/' . $project->getId(),
            'info'
        );

        // Enregistrer l'activité
        $this->activityLogger->logActivity(
            $this->getUser(),
            'a ajouté',
            $user->getPrenom() . ' ' . $user->getNom() . ' au projet',
            'project_member',
            $project->getId()
        );

        $this->addFlash('success', $user->getPrenom() . ' ' . $user->getNom() . ' a été ajouté au projet avec succès.');
        return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
    }

    #[Route('/{projectId}/members/{userId}/remove', name: 'app_project_members_remove', methods: ['POST'])]
    #[IsGranted('ROLE_CHEF_PROJET')]
    public function removeMember(
        int $projectId,
        int $userId,
        Request $request,
        UserRepository $userRepository
    ): Response {
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        // Vérifier si l'utilisateur a le droit de modifier ce projet
        $this->denyAccessUnlessGranted('EDIT', $project);

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('remove_member' . $userId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_project_members', ['id' => $projectId]);
        }

        $user = $userRepository->find($userId);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_project_members', ['id' => $projectId]);
        }

        // Vérifier si l'utilisateur est membre
        if (!$project->getMembres()->contains($user)) {
            $this->addFlash('error', 'Cet utilisateur n\'est pas membre du projet.');
            return $this->redirectToRoute('app_project_members', ['id' => $projectId]);
        }

        // Empêcher la suppression du créateur du projet
        if ($project->getCreatedBy() === $user) {
            $this->addFlash('error', 'Vous ne pouvez pas retirer le créateur du projet.');
            return $this->redirectToRoute('app_project_members', ['id' => $projectId]);
        }

        // Retirer l'utilisateur des membres
        $project->removeMembre($user);

        // Retirer également les assignations de tâches pour cet utilisateur dans ce projet
        foreach ($project->getTasks() as $task) {
            if ($task->getAssignedUser() === $user) {
                $task->setAssignedUser(null);
            }
        }
        $this->entityManager->flush();

        // Créer une notification pour l'utilisateur
        $this->notificationService->createNotification(
            $user,
            'Retrait d\'un projet',
            'Vous avez été retiré du projet "' . $project->getTitre() . '".',
            null,
            'warning'
        );

        // Enregistrer l'activité
        $this->activityLogger->logActivity(
            $this->getUser(),
            'a retiré',
            $user->getPrenom() . ' ' . $user->getNom() . ' du projet',
            'project_member',
            $project->getId()
        );

        $this->addFlash('success', $user->getPrenom() . ' ' . $user->getNom() . ' a été retiré du projet avec succès.');
        return $this->redirectToRoute('app_project_members', ['id' => $projectId]);
    }

    #[Route('/{id}/members/search', name: 'app_project_members_search', methods: ['GET'])]
    #[IsGranted('EDIT', 'project')]
    public function searchPotentialMembers(Project $project, Request $request, UserRepository $userRepository): Response
    {
        $term = $request->query->get('term', '');

        if (empty($term) || strlen($term) < 2) {
            return $this->json([
                'success' => false,
                'message' => 'Terme de recherche trop court'
            ], 400);
        }

        // Rechercher des utilisateurs qui ne sont pas déjà membres
        // et qui ont le rôle ROLE_EMPLOYE ou ROLE_CHEF_PROJET
        $users = $userRepository->searchNonProjectMembers($term, $project);

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => $user->getId(),
                'text' => $user->getPrenom() . ' ' . $user->getNom() . ' (' . $user->getEmail() . ')',
                'name' => $user->getPrenom() . ' ' . $user->getNom(),
                'email' => $user->getEmail(),
                'role' => $user->hasRole('ROLE_CHEF_PROJET') ? 'Chef de projet' : 'Employé'
            ];
        }

        return $this->json([
            'success' => true,
            'results' => $results
        ]);
    }

    #[Route('/{id}/members/change-role/{userId}', name: 'app_project_change_member_role', methods: ['POST'])]
    #[IsGranted('ROLE_DIRECTEUR')]
    public function changeMemberRole(Project $project, int $userId, Request $request, UserRepository $userRepository): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('change_role' . $userId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        $user = $userRepository->find($userId);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        // Vérifier si l'utilisateur est membre
        if (!$project->getMembres()->contains($user)) {
            $this->addFlash('error', 'Cet utilisateur n\'est pas membre du projet.');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        $newRole = $request->request->get('role');
        $allowedRoles = ['ROLE_EMPLOYE', 'ROLE_CHEF_PROJET'];

        if (!in_array($newRole, $allowedRoles)) {
            $this->addFlash('error', 'Rôle invalide.');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        // Supprimer l'ancien rôle et ajouter le nouveau
        $oldRole = $user->hasRole('ROLE_CHEF_PROJET') ? 'ROLE_CHEF_PROJET' : 'ROLE_EMPLOYE';

        // Ne rien faire si le rôle ne change pas
        if ($oldRole === $newRole) {
            $this->addFlash('info', 'L\'utilisateur a déjà ce rôle.');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        // Pour ce projet spécifique, on modifie le rôle dans la relation projet-membre
        // Implémenter selon votre modèle de données
        // Par exemple, si vous avez une entité de liaison ProjectMember :
        /*
        $projectMember = $this->entityManager->getRepository(ProjectMember::class)
            ->findOneBy(['project' => $project, 'user' => $user]);
        if ($projectMember) {
            $projectMember->setRole($newRole);
            $this->entityManager->flush();
        }
        */

        // Si vous n'avez pas d'entité de liaison, vous pouvez utiliser un attribut dans User
        // pour spécifier le rôle dans ce projet particulier.

        // Notifier l'utilisateur du changement
        $roleName = $newRole === 'ROLE_CHEF_PROJET' ? 'Chef de projet' : 'Employé';
        $this->notificationService->createNotification(
            $user,
            'Changement de rôle dans un projet',
            'Votre rôle dans le projet "' . $project->getTitre() . '" a été changé en ' . $roleName . '.',
            '/project/' . $project->getId(),
            'info'
        );

        // Enregistrer l'activité
        $this->activityLogger->logActivity(
            $this->getUser(),
            'a changé le rôle de',
            $user->getPrenom() . ' ' . $user->getNom() . ' en ' . $roleName,
            'project_member',
            $project->getId()
        );

        $this->addFlash('success', 'Le rôle de ' . $user->getPrenom() . ' ' . $user->getNom() . ' a été changé en ' . $roleName . '.');
        return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
    }


    // #[Route('/member', name: 'app_member')]
    // public function index(): Response
    // {
    //     return $this->render('member/index.html.twig', [
    //         'controller_name' => 'MemberController',
    //     ]);
    // }
}
