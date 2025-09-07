<?php

// namespace App\Controller;

// use App\Entity\User;
// use App\Repository\ProjectRepository;
// use App\Repository\UserRepository;
// use App\Service\AdminKanbanService;
// use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
// use Symfony\Component\HttpFoundation\JsonResponse;
// use Symfony\Component\HttpFoundation\Request;
// use Symfony\Component\HttpFoundation\Response;
// use Symfony\Component\Routing\Annotation\Route;
// use Symfony\Component\Security\Http\Attribute\IsGranted;

// #[Route('/admin/kanban')]
// #[IsGranted('ROLE_ADMIN')]
// class AdminKanbanController extends AbstractController
{
    public function __construct(
        private AdminKanbanService $adminKanbanService,
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // 1) Get filters from query parameters
        $filters = [
            'project_id' => $request->query->get('project_id'),
            'assigned_user' => $request->query->get('assigned_user'),
            'priority' => $request->query->get('priority', 'all'),
            'status' => $request->query->get('status', 'all'),
        ];

        // 2) Get authenticated user
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        // 3) Get Kanban data based on user role
        $data = $this->adminKanbanService->getKanbanDataByRole($user, $filters);

        // 4) Ensure all required data is present with default values
        $data = array_merge([
            'tasks' => [],
            'taskLists' => [],
            'recentActivities' => [],
            'statistics' => [
                'projectsTotal' => 0,
                'activeProjects' => 0,
                'completedTasks' => 0,
                'completionRate' => 0,
                'overdueTasks' => 0,
                'activeUsers' => 0,
                'avgTasksPerUser' => 0,
                'completedThisWeek' => 0,
            ],
            'userRole' => 'ADMIN',
        ], $data ?? []);

        // 5) Get available projects and users for filters
        $availableProjects = $this->projectRepository->findAll();
        $availableUsers = $this->userRepository->findAll();

        // 6) Prepare API endpoints for frontend
        $apiEndpoints = [
            'exportCsv' => $this->generateUrl('admin_kanban_export', ['format' => 'csv']),
            'exportJson' => $this->generateUrl('admin_kanban_export', ['format' => 'json']),
            'recentActivities' => $this->generateUrl('admin_kanban_recent_activities'),
            'moveTask' => $this->generateUrl('admin_kanban_move_task'),
            'createTask' => $this->generateUrl('admin_kanban_create_task'),
            'updateTask' => $this->generateUrl('admin_kanban_update_task', ['id' => 'TASK_ID']),
            'deleteTask' => $this->generateUrl('admin_kanban_delete_task', ['id' => 'TASK_ID']),
        ];

        // 7) Render the template with all necessary data
        return $this->render('admin/kanban/global.html.twig', [
            'data' => $data,
            'availableProjects' => $availableProjects,
            'availableUsers' => $availableUsers,
            'filters' => $filters,
            'apiEndpoints' => $apiEndpoints,
            'user_permissions' => $this->getUserPermissions()
        ]);
    }

    #[Route('/export/{format}', name: 'export', methods: ['GET'])]
    public function export(string $format): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        // Get filtered data based on current user's role and permissions
        $data = $this->adminKanbanService->getKanbanDataByRole($user);

        if ($format === 'csv') {
            return $this->exportToCsv($data);
        }
        
        if ($format === 'json') {
            return $this->exportToJson($data);
        }
        
        throw $this->createNotFoundException('Format d\'export non supporté.');
    }

    #[Route('/activities/recent', name: 'recent_activities', methods: ['GET'])]
    public function recentActivities(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        $activities = $this->adminKanbanService->getRecentActivitiesForAdmin();
        
        return $this->json([
            'success' => true,
            'activities' => $activities
        ]);
    }

    /**
     * Move a task to a new list/position
     */
    #[Route('/move-task', name: 'move_task', methods: ['POST'])]
    public function moveTask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['taskId'], $data['newListId'], $data['newPosition'])) {
            return $this->json([
                'success' => false,
                'message' => 'Données de déplacement de tâche invalides.'
            ], 400);
        }
        
        $success = $this->adminKanbanService->moveTask(
            (int)$data['taskId'],
            (int)$data['newListId'],
            (int)$data['newPosition']
        );
        
        return $this->json([
            'success' => $success,
            'message' => $success ? 'Tâche déplacée avec succès.' : 'Échec du déplacement de la tâche.'
        ]);
    }

    /**
     * Create a new quick task
     */
    #[Route('/create-task', name: 'create_task', methods: ['POST'])]
    public function createTask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['title'])) {
            return $this->json([
                'success' => false,
                'message' => 'Le titre de la tâche est requis.'
            ], 400);
        }
        
        $result = $this->adminKanbanService->createQuickTask($data);
        
        return $this->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Global search across tasks, projects, and users
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query)) {
            return $this->json([
                'success' => false,
                'message' => 'La requête de recherche est vide.'
            ], 400);
        }
        
        $results = $this->adminKanbanService->globalSearch($query);
        
        return $this->json([
            'success' => true,
            'results' => $results
        ]);
    }

    /**
     * Export data to CSV format Version test 2 
     */
    private function exportToCsv(array $data): Response
    {
        // Create CSV content
        $csv = [];
        
        // Add header
        $csv[] = [
            'ID', 'Titre', 'Description', 'Projet', 'Liste de tâches',
            'Statut', 'Priorité', 'Date d\'échéance', 'Assigné à',
            'Créé le', 'Modifié le'
        ];
        
        // Add tasks
        foreach ($data['tasks'] as $task) {
            $csv[] = [
                $task->getId(),
                $task->getTitle(),
                $task->getDescription(),
                $task->getProject() ? $task->getProject()->getTitre() : '',
                $task->getTaskList() ? $task->getTaskList()->getNom() : '',
                $task->getStatus(),
                $task->getPriority(),
                $task->getDueDate() ? $task->getDueDate()->format('Y-m-d') : '',
                $task->getAssignedTo() ? $task->getAssignedTo()->getFullName() : '',
                $task->getCreatedAt() ? $task->getCreatedAt()->format('Y-m-d H:i') : '',
                $task->getUpdatedAt() ? $task->getUpdatedAt()->format('Y-m-d H:i') : ''
            ];
        }
        
        // Convert to CSV string
        $output = fopen('php://temp', 'w');
        foreach ($csv as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        // Create response
        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="kanban_export_' . date('Y-m-d') . '.csv"');
        
        return $response;
    }
    
    /**
     * Export data to JSON format
     */
    private function exportToJson(array $data): JsonResponse
    {
        $exportData = [
            'metadata' => [
                'exportedAt' => (new \DateTimeImmutable())->format('c'),
                'totalTasks' => count($data['tasks']),
                'totalProjects' => $data['statistics']['projectsTotal'] ?? 0,
                'completedTasks' => $data['statistics']['completedTasks'] ?? 0,
                'completionRate' => $data['statistics']['completionRate'] ?? 0,
            ],
            'projects' => [],
            'taskLists' => [],
            'tasks' => [],
        ];
        
        // Format projects
        foreach ($data['projects'] as $project) {
            $exportData['projects'][] = [
                'id' => $project->getId(),
                'title' => $project->getTitre(),
                'description' => $project->getDescription(),
                'status' => $project->getStatut(),
                'startDate' => $project->getDateDebut() ? $project->getDateDebut()->format('Y-m-d') : null,
                'endDate' => $project->getDateFin() ? $project->getDateFin()->format('Y-m-d') : null,
                'createdAt' => $project->getCreatedAt() ? $project->getCreatedAt()->format('c') : null,
            ];
        }
        
        // Format task lists
        foreach ($data['taskLists'] as $list) {
            $exportData['taskLists'][] = [
                'id' => $list->getId(),
                'name' => $list->getNom(),
                'position' => $list->getPosition(),
                'projectId' => $list->getProject() ? $list->getProject()->getId() : null,
                'createdAt' => $list->getCreatedAt() ? $list->getCreatedAt()->format('c') : null,
            ];
        }
        
        // Format tasks
        foreach ($data['tasks'] as $task) {
            $exportData['tasks'][] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'description' => $task->getDescription(),
                'status' => $task->getStatus(),
                'priority' => $task->getPriority(),
                'dueDate' => $task->getDueDate() ? $task->getDueDate()->format('c') : null,
                'projectId' => $task->getProject() ? $task->getProject()->getId() : null,
                'taskListId' => $task->getTaskList() ? $task->getTaskList()->getId() : null,
                'assignedTo' => $task->getAssignedTo() ? [
                    'id' => $task->getAssignedTo()->getId(),
                    'fullName' => $task->getAssignedTo()->getFullName(),
                    'email' => $task->getAssignedTo()->getEmail(),
                ] : null,
                'createdAt' => $task->getCreatedAt() ? $task->getCreatedAt()->format('c') : null,
                'updatedAt' => $task->getUpdatedAt() ? $task->getUpdatedAt()->format('c') : null,
            ];
        }
        
        // Create response
        $response = new JsonResponse($exportData);
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', 'attachment; filename="kanban_export_' . date('Y-m-d') . '.json"');
        
        return $response;
    }

    /**
     * Get user permissions based on roles
     */
    private function getUserPermissions(): array
    {
        return [
            'can_edit' => $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_CHEF_PROJET'),
            'can_delete' => $this->isGranted('ROLE_ADMIN'),
            'can_manage_users' => $this->isGranted('ROLE_ADMIN'),
            'can_export' => true,
            'can_create_tasks' => $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_CHEF_PROJET'),
            'can_edit_tasks' => $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_CHEF_PROJET'),
            'can_move_tasks' => true,
            'can_view_reports' => $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_CHEF_PROJET'),
        ];
    }
}
