<?php

namespace App\Controller;

use App\Repository\ProjectRepository;
use App\Repository\TaskListRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use App\Service\AdminKanbanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/kanban')]
#[IsGranted('ROLE_ADMIN')]
class AdminKanbanController extends AbstractController
{
    public function __construct(
        private AdminKanbanService $adminKanbanService,
        private ActivityLogger $activityLogger,
        private ProjectRepository $projectRepository,
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private TaskListRepository $taskListRepository
    ) {}
// Assigner un utilisateur à un projet
#[Route('/project/assign/{userId}/{projectId}', name: 'assign_user_to_project')]
public function assignUserToProject(int $userId, int $projectId): Response
{
    $user = $this->userRepository->find($userId);
    $project = $this->projectRepository->find($projectId);
    
    $result = $this->adminKanbanService->assignUserToProject($user, $project, $this->getUser());
    return $this->json($result);
}
    /**
     * 🎯 PAGE PRINCIPALE - Vue Kanban Globale Admin
     */
    #[Route('/', name: 'admin_kanban_global', methods: ['GET'])]
    public function globalKanbanView(Request $request): Response
    {
        // Récupération des filtres
        $filters = [
            'project_id' => $request->query->get('project_id'),
            'assigned_user' => $request->query->get('assigned_user'),
            'priority' => $request->query->get('priority', 'all'),
            'status' => $request->query->get('status', 'all'),
            'due_soon' => $request->query->get('due_soon', false)
        ];

        $kanbanData = $this->adminKanbanService->getAllKanbanData($filters);

        return $this->render('admin/kanban/global.html.twig', [
            'data' => $kanbanData,
            'filters' => $filters,
            'availableProjects' => $this->projectRepository->findAll(),
            'availableUsers' => $this->userRepository->findActiveUsers()
        ]);
    }

    /**
     * 🔄 API - Déplacer une tâche (Drag & Drop)
     */
    #[Route('/move-task', name: 'admin_kanban_move_task', methods: ['POST'])]
    public function moveTask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $result = $this->adminKanbanService->moveTask(
            $data['taskId'],
            $data['newListId'],
            $data['newPosition']
        );

        return $this->json($result);
    }

    /**
     * ➕ API - Création rapide de tâche
     */
    #[Route('/quick-task', name: 'admin_kanban_quick_task', methods: ['POST'])]
    public function createQuickTask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $result = $this->adminKanbanService->createQuickTask($data);

        return $this->json($result);
    }

    /**
     * 📊 API - Statistiques en temps réel
     */
    #[Route('/statistics', name: 'admin_kanban_statistics', methods: ['GET'])]
    public function getStatistics(): JsonResponse
    {
        $statistics = $this->adminKanbanService->getGlobalStatistics();
        $performanceMetrics = $this->adminKanbanService->getPerformanceMetrics();
        $workloadDistribution = $this->adminKanbanService->getWorkloadDistribution();

        return $this->json([
            'statistics' => $statistics,
            'performance' => $performanceMetrics,
            'workload' => $workloadDistribution
        ]);
    }

    /**
     * 🔍 API - Recherche globale
     */
    #[Route('/search', name: 'admin_kanban_search', methods: ['GET'])]
    public function globalSearch(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json(['results' => []]);
        }

        $results = $this->adminKanbanService->globalSearch($query);

        return $this->json(['results' => $results]);
    }

    /**
     * 📅 Vue Calendrier Admin - Toutes les tâches
     */
    #[Route('/calendar', name: 'admin_kanban_calendar', methods: ['GET'])]
    public function calendarView(): Response
    {
        return $this->render('admin/kanban/calendar.html.twig');
    }

    /**
     * 📈 Dashboard Analytics
     */
    #[Route('/analytics', name: 'admin_kanban_analytics', methods: ['GET'])]
    public function analyticsView(): Response
    {
        $analytics = [
            'statistics' => $this->adminKanbanService->getGlobalStatistics(),
            'performance' => $this->adminKanbanService->getPerformanceMetrics(),
            'workload' => $this->adminKanbanService->getWorkloadDistribution(),
            'recentActivities' => $this->adminKanbanService->getRecentActivities()
        ];

        return $this->render('admin/kanban/analytics.html.twig', [
            'analytics' => $analytics
        ]);
    }

    /**
     * 👥 Gestion des utilisateurs depuis Kanban
     */
    #[Route('/users', name: 'admin_kanban_users', methods: ['GET'])]
    public function usersManagement(): Response
    {
        $users = $this->userRepository->findActiveUsers();
        $userStats = [];

        foreach ($users as $user) {
            $tasks = $this->taskRepository->findByAssignedUser($user);
            $userStats[] = [
                'user' => $user,
                'totalTasks' => count($tasks),
                'completedTasks' => count(array_filter($tasks, fn($t) => $t->getStatut() === 'TERMINER')),
                'activeTasks' => count(array_filter($tasks, fn($t) => $t->getStatut() !== 'TERMINER')),
                'overdueTests' => count(array_filter($tasks, function ($t) {
                    return $t->getDeadline() && $t->getDeadline() < new \DateTime() && $t->getStatut() !== 'TERMINER';
                }))
            ];
        }

        return $this->render('admin/kanban/users.html.twig', [
            'userStats' => $userStats
        ]);
    }

    /**
     * 🏗️ Gestion des projets depuis Kanban
     */
    #[Route('/projects', name: 'admin_kanban_projects', methods: ['GET'])]
    public function projectsManagement(): Response
    {
        $projects = $this->projectRepository->findRecentWithStats();

        return $this->render('admin/kanban/projects.html.twig', [
            'projects' => $projects
        ]);
    }

    /**
     * 📋 Export des données Kanban
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

        $data = $this->adminKanbanService->getAllKanbanData($filters);

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
     * 🔄 API - Actualisation des données
     */
    #[Route('/refresh', name: 'admin_kanban_refresh', methods: ['GET'])]
    public function refreshData(Request $request): JsonResponse
    {
        $filters = $request->query->all();
        $data = $this->adminKanbanService->getAllKanbanData($filters);

        return $this->json([
            'success' => true,
            'data' => $data,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * 🚨 API - Alertes et notifications admin
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
     * Export CSV privé
     */
    private function exportToCsv(array $data): Response
    {
        $csv = "Project,Task,Status,Priority,Assigned_To,Deadline\n";

        foreach ($data['tasks'] as $task) {
            $assignedUsers = [];
            foreach ($task->getTaskUsers() as $taskUser) {
                $assignedUsers[] = $taskUser->getUser()->getPrenom() . ' ' . $taskUser->getUser()->getNom();
            }

            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $task->getTaskList()->getProject()->getTitre(),
                $task->getTitle(),
                $task->getStatut(),
                $task->getPriority(),
                implode('; ', $assignedUsers),
                $task->getDeadline()?->format('Y-m-d') ?? ''
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="kanban-export-' . date('Y-m-d') . '.csv"');

        return $response;
    }
}
