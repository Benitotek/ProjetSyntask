<?php

namespace App\Controller;

use App\Service\AdminKanbanService;
use App\Security\Voter\ProjectVoter;
use App\Security\Voter\TaskVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/kanban')]
#[IsGranted('ROLE_USER')]
class RoleBasedKanbanController extends AbstractController
{
    public function __construct(
        private AdminKanbanService $adminKanbanService
    ) {}

    /**  
     * 🎯 ROUTE PRINCIPALE - Dashboard Kanban adapté au rôle  
     */
    #[Route('/dashboard', name: 'kanban_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $user = $this->getUser();
        $filters = $this->getFiltersFromRequest($request);

        // Récupérer les données selon le rôle  
        $kanbanData = $this->adminKanbanService->getKanbanDataByRole($user, $filters);

        // Utilisateurs assignables selon le rôle  
        $assignableUsers = $this->adminKanbanService->getAssignableUsers($user);

        // Template selon le rôle  
        $template = $this->getTemplateByRole($user);

        return $this->render($template, [
            'data' => $kanbanData,
            'filters' => $filters,
            'assignableUsers' => $assignableUsers,
            'currentUser' => $user,
            'userPermissions' => $this->getUserPermissions($user)
        ]);
    }

    /**  
     * 🔄 API - Déplacer une tâche avec vérification des droits  
     */
    #[Route('/move-task', name: 'kanban_move_task', methods: ['POST'])]
    public function moveTask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        $result = $this->adminKanbanService->moveTaskWithRoleCheck(
            $data['taskId'],
            $data['newListId'],
            $data['newPosition'],
            $user
        );

        return $this->json($result);
    }

    /**  
     * 👥 API - Assigner un utilisateur à un projet  
     */
    #[Route('/assign-user-project', name: 'kanban_assign_user_project', methods: ['POST'])]
    public function assignUserToProject(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        $result = $this->adminKanbanService->assignUserToProject(
            $data['userId'],
            $data['projectId'],
            $user
        );

        return $this->json($result);
    }

    /**
     * 📋 API - Assigner un utilisateur à une tâche
     */
    #[Route('/assign-user-task', name: 'kanban_assign_user_task', methods: ['POST'])]
    public function assignUserToTask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        $result = $this->adminKanbanService->assignUserToTask(
            $data['userId'],
            $data['taskId'],
            $user
        );

        return $this->json($result);
    }

    /**
     * 👑 API - Promouvoir un utilisateur en chef de projet
     */
    #[Route('/promote-chef-projet', name: 'kanban_promote_chef_projet', methods: ['POST'])]
    #[IsGranted('ROLE_DIRECTEUR')] // Seuls Admin et Directeur peuvent promouvoir
    public function promoteToChefProjet(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        $result = $this->adminKanbanService->promoteToChefProjet(
            $data['userId'],
            $data['projectId'],
            $user
        );

        return $this->json($result);
    }

    /**
     * 🔄 API - Actualisation des données selon le rôle
     */
    #[Route('/refresh-data', name: 'kanban_refresh_data', methods: ['GET'])]
    public function refreshData(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $filters = $this->getFiltersFromRequest($request);

        $data = $this->adminKanbanService->getKanbanDataByRole($user, $filters);

        return $this->json([
            'success' => true,
            'data' => $data,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'userRole' => $this->getUserHighestRole($user)
        ]);
    }

    /**
     * 👥 API - Récupérer les utilisateurs assignables
     */
    #[Route('/assignable-users/{projectId?}', name: 'kanban_assignable_users', methods: ['GET'])]
    public function getAssignableUsers(Request $request, ?int $projectId = null): JsonResponse
    {
        $user = $this->getUser();
        $project = null;

        if ($projectId) {
            $project = $this->assignUserToProject(
                $request,
                $projectId
            );
        }

        $assignableUsers = $this->adminKanbanService->getAssignableUsers($user, $project);

        return $this->json([
            'users' => array_map(function ($u) {
                return [
                    'id' => $u->getId(),
                    'nom' => $u->getNom(),
                    'prenom' => $u->getPrenom(),
                    'email' => $u->getEmail(),
                    'role' => $u->getRole()->value,
                    'initials' => $u->getInitials(),
                    'avatar' => $u->getAvatar()
                ];
            }, $assignableUsers)
        ]);
    }

    /**
     * 📊 Dashboard spécifique Admin
     */
    #[Route('/admin-dashboard', name: 'kanban_admin_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(): Response
    {
        $user = $this->getUser();
        $kanbanData = $this->adminKanbanService->getAllKanbanData();
        $assignableUsers = $this->adminKanbanService->getAssignableUsers($user);

        return $this->render('kanban/admin/dashboard.html.twig', [
            'data' => $kanbanData,
            'assignableUsers' => $assignableUsers,
            'currentUser' => $user,
            'userPermissions' => $this->getAdminPermissions()
        ]);
    }

    /**
     * 📊 Dashboard spécifique Directeur
     */
    #[Route('/directeur-dashboard', name: 'kanban_directeur_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_DIRECTEUR')]
    public function directeurDashboard(): Response
    {
        $user = $this->getUser();
        $kanbanData = $this->adminKanbanService->getDirecteurKanbanData($user);
        $assignableUsers = $this->adminKanbanService->getAssignableUsers($user);

        return $this->render('kanban/directeur/dashboard.html.twig', [
            'data' => $kanbanData,
            'assignableUsers' => $assignableUsers,
            'currentUser' => $user,
            'userPermissions' => $this->getDirecteurPermissions()
        ]);
    }

    /**
     * 📊 Dashboard spécifique Chef de Projet
     */
    #[Route('/chef-projet-dashboard', name: 'kanban_chef_projet_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_CHEF_PROJET')]
    public function chefProjetDashboard(): Response
    {
        $user = $this->getUser();
        $kanbanData = $this->adminKanbanService->getChefProjetKanbanData($user);
        $assignableUsers = $this->adminKanbanService->getAssignableUsers($user);

        return $this->render('kanban/chef-projet/dashboard.html.twig', [
            'data' => $kanbanData,
            'assignableUsers' => $assignableUsers,
            'currentUser' => $user,
            'userPermissions' => $this->getChefProjetPermissions()
        ]);
    }

    /**
     * 📊 Dashboard spécifique Employé
     */
    #[Route('/employe-dashboard', name: 'kanban_employe_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYE')]
    public function employeDashboard(): Response
    {
        $user = $this->getUser();
        $kanbanData = $this->adminKanbanService->getEmployeKanbanData($user);

        return $this->render('kanban/employe/dashboard.html.twig', [
            'data' => $kanbanData,
            'currentUser' => $user,
            'userPermissions' => $this->getEmployePermissions()
        ]);
    }

    // === MÉTHODES PRIVÉES ===

    /**
     * Récupère les filtres depuis la requête
     */
    private function getFiltersFromRequest(Request $request): array
    {
        return [
            'project_id' => $request->query->get('project_id'),
            'assigned_user' => $request->query->get('assigned_user'),
            'priority' => $request->query->get('priority', 'all'),
            'status' => $request->query->get('status', 'all'),
            'due_soon' => $request->query->get('due_soon', false),
            'search' => $request->query->get('search', '')
        ];
    }

    /**
     * Détermine le template selon le rôle
     */
    private function getTemplateByRole($user): string
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            return 'kanban/admin/dashboard.html.twig';
        } elseif (in_array('ROLE_DIRECTEUR', $roles)) {
            return 'kanban/directeur/dashboard.html.twig';
        } elseif (in_array('ROLE_CHEF_PROJET', $roles)) {
            return 'kanban/chef-projet/dashboard.html.twig';
        } else {
            return 'kanban/employe/dashboard.html.twig';
        }
    }

    /**
     * Récupère le rôle le plus élevé
     */
    private function getUserHighestRole($user): string
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) return 'ADMIN';
        if (in_array('ROLE_DIRECTEUR', $roles)) return 'DIRECTEUR';
        if (in_array('ROLE_CHEF_PROJET', $roles)) return 'CHEF_PROJET';
        return 'EMPLOYE';
    }

    /**
     * Permissions spécifiques par rôle
     */
    private function getUserPermissions($user): array
    {
        $roles = $user->getRoles();

        return [
            'canCreateProject' => in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles),
            'canEditAllProjects' => in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles),
            'canDeleteProjects' => in_array('ROLE_ADMIN', $roles),
            'canManageUsers' => in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles),
            'canPromoteUsers' => in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles),
            'canAssignToAllProjects' => in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles),
            'canAssignToOwnProjects' => in_array('ROLE_CHEF_PROJET', $roles),
            'canMoveTasksBetweenProjects' => in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles),
            'canViewAllProjects' => in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles),
            'role' => $this->getUserHighestRole($user)
        ];
    }

    private function getAdminPermissions(): array
    {
        return [
            'canCreateProject' => true,
            'canEditAllProjects' => true,
            'canDeleteProjects' => true,
            'canManageUsers' => true,
            'canPromoteUsers' => true,
            'canAssignToAllProjects' => true,
            'canMoveTasksBetweenProjects' => true,
            'canViewAllProjects' => true,
            'canArchiveProjects' => true,
            'canExportData' => true,
            'role' => 'ADMIN'
        ];
    }

    private function getDirecteurPermissions(): array
    {
        return [
            'canCreateProject' => true,
            'canEditAllProjects' => true,
            'canDeleteProjects' => false,
            'canManageUsers' => true,
            'canPromoteUsers' => true,
            'canAssignToAllProjects' => true,
            'canMoveTasksBetweenProjects' => true,
            'canViewAllProjects' => true,
            'canArchiveProjects' => true,
            'canExportData' => true,
            'role' => 'DIRECTEUR'
        ];
    }

    private function getChefProjetPermissions(): array
    {
        return [
            'canCreateProject' => false,
            'canEditAllProjects' => false,
            'canDeleteProjects' => false,
            'canManageUsers' => false,
            'canPromoteUsers' => false,
            'canAssignToAllProjects' => false,
            'canAssignToOwnProjects' => true,
            'canMoveTasksBetweenProjects' => false,
            'canViewAllProjects' => false,
            'canEditOwnProjects' => true,
            'role' => 'CHEF_PROJET'
        ];
    }

    private function getEmployePermissions(): array
    {
        return [
            'canCreateProject' => false,
            'canEditAllProjects' => false,
            'canDeleteProjects' => false,
            'canManageUsers' => false,
            'canPromoteUsers' => false,
            'canAssignToAllProjects' => false,
            'canAssignToOwnProjects' => false,
            'canMoveTasksBetweenProjects' => false,
            'canViewAllProjects' => false,
            'canEditOwnTasks' => true,
            'canCommentTasks' => true,
            'role' => 'EMPLOYE'
        ];
    }
}
