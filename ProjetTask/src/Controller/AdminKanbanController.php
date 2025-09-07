<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\AdminKanbanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/kanban')]
#[IsGranted('ROLE_ADMIN')]
class AdminKanbanController extends AbstractController
{
    public function __construct(
        private AdminKanbanService $adminKanbanService,
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // 1) Get filters from query parameters
        $filters = [
            'project_id'    => $request->query->get('project_id'),
            'assigned_user' => $request->query->get('assigned_user'),
            'priority'      => $request->query->get('priority', 'all'),
            'status'        => $request->query->get('status', 'all'),
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
            'projects' => [],
            'users' => [],
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
                'not_started_tasks' => 0,
                'in_progress_tasks' => 0,
                'in_review_tasks' => 0,
                'completed_tasks' => 0,
                'high_priority_tasks' => 0,
                'medium_priority_tasks' => 0,
                'low_priority_tasks' => 0,
            ],
            'userRole' => 'ADMIN',
        ], $data ?? []);

        // 5) Get available projects and users for filters if not already in data
        if (empty($data['projects'])) {
            $data['projects'] = $this->projectRepository->findAll();
        }
        
        if (empty($data['users'])) {
            $data['users'] = $this->userRepository->findAll();
        }

        // 6) Calculate any missing statistics
        if (empty($data['statistics']['not_started_tasks'])) {
            $data['statistics']['not_started_tasks'] = count(array_filter(
                $data['tasks'], 
                fn($task) => $task->getStatus() === 'A_FAIRE' || $task->getStatus() === 'TODO'
            ));
        }
        
        if (empty($data['statistics']['in_progress_tasks'])) {
            $data['statistics']['in_progress_tasks'] = count(array_filter(
                $data['tasks'], 
                fn($task) => $task->getStatus() === 'EN_COURS' || $task->getStatus() === 'IN_PROGRESS'
            ));
        }
        
        if (empty($data['statistics']['in_review_tasks'])) {
            $data['statistics']['in_review_tasks'] = count(array_filter(
                $data['tasks'], 
                fn($task) => $task->getStatus() === 'REVIEW'
            ));
        }
        
        if (empty($data['statistics']['completed_tasks'])) {
            $data['statistics']['completed_tasks'] = count(array_filter(
                $data['tasks'], 
                fn($task) => $task->getStatus() === 'DONE'
            ));
        }
        
        // 7) Calculate priority-based statistics if not already set
        if (empty($data['statistics']['high_priority_tasks'])) {
            $data['statistics']['high_priority_tasks'] = count(array_filter(
                $data['tasks'], 
                fn($task) => $task->getPriority() === 'HIGH'
            ));
        }
        
        if (empty($data['statistics']['medium_priority_tasks'])) {
            $data['statistics']['medium_priority_tasks'] = count(array_filter(
                $data['tasks'], 
                fn($task) => $task->getPriority() === 'MEDIUM'
            ));
        }
        
        if (empty($data['statistics']['low_priority_tasks'])) {
            $data['statistics']['low_priority_tasks'] = count(array_filter(
                $data['tasks'], 
                fn($task) => $task->getPriority() === 'LOW'
            ));
        }

        // 8) Prepare API endpoints for frontend
        $apiEndpoints = [
            'exportCsv' => $this->generateUrl('admin_kanban_export', ['format' => 'csv']),
            'exportJson' => $this->generateUrl('admin_kanban_export', ['format' => 'json']),
            'recentActivities' => $this->generateUrl('admin_kanban_recent_activities'),
            'moveTask' => $this->generateUrl('admin_kanban_move_task'),
            'createTask' => $this->generateUrl('admin_kanban_create_task'),
            'updateTask' => $this->generateUrl('admin_kanban_update_task', ['id' => 'TASK_ID']),
            'deleteTask' => $this->generateUrl('admin_kanban_delete_task', ['id' => 'TASK_ID']),
        ];

        // 9) Render the enhanced template with all necessary data
        return $this->render('admin/kanban/global_enhanced.html.twig', [
            'data' => $data,
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
     * Export data to CSV format
     */
    private function exportToCsv($data) // private function exportToCsv(array $data): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="kanban_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes
        fputcsv($output, [
            'ID', 'Titre', 'Description', 'Statut', 'Priorité', 'Date d\'échéance',
            'Projet', 'Liste de tâches', 'Assigné à', 'Créé le', 'Mis à jour le'
        ]);
        
        // Données des tâches
        foreach ($data['tasks'] as $task) {
            fputcsv($output, [
                $task->getId(),
                $task->getTitre(),
                substr($task->getDescription() ?? '', 0, 100), // Limite la description
                $task->getStatut(),
                $task->getPriorite(),
                $task->getDateEcheance() ? $task->getDateEcheance()->format('Y-m-d') : '',
                $task->getProjet() ? $task->getProjet()->getTitre() : '',
                $task->getListeTaches() ? $task->getListeTaches()->getNom() : '',
                $task->getUtilisateurAssignation() ? 
                    $task->getUtilisateurAssignation()->getPrenom() . ' ' . 
                    $task->getUtilisateurAssignation()->getNom() : '',
                $task->getDateCreation() ? $task->getDateCreation()->format('Y-m-d H:i:s') : '',
                $task->getDateMiseAJour() ? $task->getDateMiseAJour()->format('Y-m-d H:i:s') : ''
            ]);
        }
        
        fclose($output);
        return $response;
    }

    /**
     * Export des données Kanban
     */
    #[Route('/export', name: 'admin_kanban_export', methods: ['GET'])]
    public function exportData(Request $request): Response
    {
        $format = $request->query->get('format', 'csv'); // csv, json, xlsx
        $filters = [
            'project_id' => $request->query->get('project_id'),
            'status' => $request->query->get('status', 'all'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to')
        ];

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }
        
        $data = $this->adminKanbanService->getKanbanDataByRole($user, $filters);

        switch ($format) {
            case 'json':
                return $this->json($data);

            case 'csv':
                return $this->exportToCsv($data);

            default:
                return $this->json(['error' => 'Format non supporté']);
        }
    }

    /**
     * API - Actualisation des données
     */
    #[Route('/refresh', name: 'admin_kanban_refresh', methods: ['GET'])]
    public function refreshData(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }
        
        $filters = $request->query->all();
        $data = $this->adminKanbanService->getKanbanDataByRole($user, $filters);

        return $this->json([
            'success' => true,
            'data' => $data,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

// ...
    /**
     * API - Récupération des alertes
     */
    #[Route('/alerts', name: 'admin_kanban_alerts', methods: ['GET'])]
    public function getAlerts(): JsonResponse
    {
        $overdueTasks = $this->adminKanbanService->getOverdueTasks();
        $tasksDueSoon = $this->taskRepository->findTasksWithDeadlineApproaching();
        $inactiveUsers = $this->userRepository->findInactiveUsers(); // À créer si nécessaire

        $alerts = [
            'overdue' => [
                'count' => count($overdueTasks),
                'tasks' => array_slice($overdueTasks, 0, 5) // Top 5
            ],
            'due_soon' => [
                'count' => count($tasksDueSoon),
                'tasks' => array_slice($tasksDueSoon, 0, 5)
            ],
            'inactive_users' => [
                'count' => count($inactiveUsers ?? []),
                'users' => $inactiveUsers ?? []
            ]
        ];

        return $this->json($alerts);
    }

    /**
     * Exporte les données au format CSV avec encodage UTF-8 et gestion des caractères spéciaux
     * 
     * @param array $data Les données à exporter
     * @return Response La réponse HTTP contenant le fichier CSV
     * @throws \RuntimeException En cas d'erreur lors de la génération du CSV
     */
    private function exportToCsv(array $data): Response
    {
        try {
            // Vérifier si les tâches existent
            if (!isset($data['tasks']) || !is_array($data['tasks'])) {
                throw new \InvalidArgumentException('Aucune donnée de tâche à exporter');
            }

            // Utiliser la mémoire tampon de sortie pour de meilleures performances
            ob_start();
            $output = fopen('php://output', 'w');
            
            // Ajouter le BOM pour l'UTF-8 (pour Excel)
            fwrite($output, "\xEF\xBB\xBF");
            
            // En-têtes du CSV
            $headers = ['Projet', 'Tâche', 'Statut', 'Priorité', 'Assigné à', 'Date limite'];
            fputcsv($output, $headers, ';', '"', '\\');

            foreach ($data['tasks'] as $task) {
                if (!is_object($task) || !method_exists($task, 'getTaskUsers') || !method_exists($task, 'getTaskList')) {
                    continue; // Ignorer les entrées invalides
                }

                try {
                    // Récupérer les utilisateurs assignés
                    $assignedUsers = [];
                    foreach ($task->getTaskUsers() as $taskUser) {
                        if ($taskUser->getUser()) {
                            $fullName = trim(implode(' ', [
                                $taskUser->getUser()->getPrenom() ?? '',
                                $taskUser->getUser()->getNom() ?? ''
                            ]));
                            if (!empty($fullName)) {
                                $assignedUsers[] = $fullName;
                            }
                        }
                    }

                    // Récupérer le projet en toute sécurité
                    $projectTitle = '';
                    try {
                        $projectTitle = $task->getTaskList()?->getProject()?->getTitre() ?? '';
                    } catch (\Exception $e) {
                        // En cas d'erreur, on laisse le titre vide
                    }

                    // Préparer les données de la ligne avec des valeurs par défaut sécurisées
                    $rowData = [
                        $projectTitle,
                        $task->getTitle() ?? '',
                        $task->getStatut() ?? '',
                        $task->getPriority() ?? '',
                        implode('; ', $assignedUsers),
                        $task->getDeadline() ? $task->getDeadline()->format('Y-m-d') : ''
                    ];

                    // Écrire la ligne avec échappement des caractères spéciaux
                    fputcsv($output, $rowData, ';', '"', '\\');
                    
                } catch (\Exception $e) {
                    // Logger l'erreur mais continuer avec les autres tâches
                    error_log(sprintf('Erreur lors du traitement d\'une tâche: %s', $e->getMessage()));
                    continue;
                }
            }

            // Récupérer le contenu du tampon de sortie
            $csvContent = ob_get_clean();
            
            if ($csvContent === false) {
                throw new \RuntimeException('Erreur lors de la génération du contenu CSV');
            }

            // Créer la réponse avec les en-têtes appropriés
            $response = new Response($csvContent, Response::HTTP_OK, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => sprintf(
                    'attachment; filename="kanban-export-%s.csv"',
                    (new \DateTime())->format('Y-m-d-Hi')
                ),
                'Cache-Control' => 'private, must-revalidate',
                'Pragma' => 'no-cache',
                'Content-Length' => strlen($csvContent)
            ]);

            return $response;
            
        } catch (\Exception $e) {
            // Nettoyer le tampon de sortie en cas d'erreur
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            throw new \RuntimeException('Erreur lors de la génération du fichier CSV: ' . $e->getMessage(), 0, $e);
        } finally {
            // S'assurer que la ressource est fermée
            if (isset($output) && is_resource($output)) {
                fclose($output);
            }
        }
    }

    /**
     * Échappe les valeurs pour le format CSV (conservée pour compatibilité)
     * 
     * @deprecated Cette méthode n'est plus utilisée car fputcsv gère automatiquement l'échappement
     */
    private function escapeCsvValue(string $value): string
    {
        // fputcsv s'occupe maintenant de l'échappement
        return $value;
    }
}
