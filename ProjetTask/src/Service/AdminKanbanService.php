<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskList;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\TaskListRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdminKanbanService
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private TaskRepository $taskRepository,
        private TaskListRepository $taskListRepository,
        private UserRepository $userRepository,
        private ActivityRepository $activityRepository,
        private EntityManagerInterface $em,
        private KanbanService $kanbanService, // votre service métier de move
        private ActivityLogger $activityLogger,
        private NotificationService $notificationService,

    ) {}

    public function getKanbanDataByRole(User $user, array $filters = []): array
    {
        // Simplifié: admin voit tout
        $projects = $this->projectRepository->findAll();
        $taskLists = $this->taskListRepository->findAll();
        $tasks = $this->taskRepository->findAll();
        $statistics = $this->calculateStatistics($projects, $tasks);
        $recentActivities = $this->getRecentActivitiesForProjects($projects);
        return [
            'projects' => $projects,
            'taskLists' => $taskLists,
            'tasks' => $tasks,
            'statistics' => $statistics,
            'recentActivities' => $recentActivities,
            'filters' => $filters,
        ];
    }


    public function getExportData(): array
    {
        return [
            'tasks' => $this->taskRepository->findAll(),
        ];
    }

    public function getRecentActivitiesForAdmin(): array
    {
        // Simplifié: dernières activités globales
        $activities = $this->activityRepository->findBy([], ['dateCreation' => 'DESC'], 20);
        return array_map(function ($a) {
            return [
                'type' => (string) $a->getType()->value ?? (string) $a->getType(),
                'description' => $a->getAction(),
                'dateCreation' => $a->getDateCreation()?->format(DATE_ATOM),
                'user' => $a->getUser() ? ($a->getUser()->getPrenom() . ' ' . $a->getUser()->getNom()) : null,
                'targetUrl' => method_exists($a, 'getTargetUrl') ? $a->getTargetUrl() : null,
            ];
        }, $activities);
    }

    public function moveTaskPersist(int $taskId, int $newListId, int $position, User $by): array
    {
        $task = $this->taskRepository->find($taskId);
        $list = $this->taskListRepository->find($newListId);
        if (!$task || !$list) {
            return ['success' => false, 'message' => 'Task or list not found'];
        }

        // Règles métier et réindexation
        $this->kanbanService->moveTask($task, $list, $position);
        $this->em->flush();

        // Journaliser + notifier (optionnel/selon vos besoins)

        $this->activityLogger->logTaskMove($task, $by, $list->getProject());
        // $this->notificationService->notifyTaskMove($task, $by);

        return ['success' => true];
    }

    private function applyFilters(array $tasks, array $filters): array
    {
        return array_values(array_filter($tasks, function (Task $t) use ($filters) {
            // Filtre projet
            if (!empty($filters['project_id'])) {
                $project = $t->getTaskList()?->getProject();
                if (!$project || $project->getId() !== (int)$filters['project_id']) {
                    return false;
                }
            }
            // Filtre user assigné
            if (!empty($filters['assigned_user'])) {
                $u = $t->getAssignedUser();
                if (!$u || $u->getId() !== (int)$filters['assigned_user']) {
                    return false;
                }
            }
            // Filtre priority
            if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
                // Adapter selon votre enum/champ
                $p = method_exists($t, 'getPriorite') ? $t->getPriorite() : (method_exists($t, 'getPriority') ? $t->getPriority() : null);
                if ((string)$p !== (string)$filters['priority']) {
                    return false;
                }
            }
            // Filtre status
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $s = method_exists($t, 'getStatus') ? $t->getStatus() : $t->getStatut();
                // Comparaison enum vs string: convertir en string
                $sv = \is_object($s) && method_exists($s, 'value') ? $s->value : (string)$s;
                if ($sv !== (string)$filters['status']) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function calculateStatistics(array $projects, array $tasks): array
    {
        $totalTasks = count($tasks);
        $completed = 0;
        $inProgress = 0;
        $overdue = 0;
        $users = [];
        $now = new \DateTimeImmutable();

        foreach ($tasks as $t) {
            $s = method_exists($t, 'getStatus') ? $t->getStatus() : $t->getStatut();
            $sv = \is_object($s) && method_exists($s, 'value') ? $s->value : (string)$s;
            if ($sv === 'TERMINER') $completed++;
            if ($sv === 'EN_COURS') $inProgress++;
            $due = method_exists($t, 'getDueDate') ? $t->getDueDate() : $t->getDateButoir();
            if ($due instanceof \DateTimeInterface && $due < $now && $sv !== 'TERMINER') $overdue++;
            if ($t->getAssignedUser()) $users[$t->getAssignedUser()->getId()] = true;
        }

        return [
            // Remarquez que votre Twig attend "statistics.totalProjects" et non "projectsTotal"
            'totalProjects' => count($projects),
            'activeProjects' => count($projects), // simple pour la démo
            'totalTasks' => $totalTasks,
            'overdueTasks' => $overdue,
            'totalUsers' => count($users),
            // Clés supplémentaires si besoin ailleurs
            'completionRate' => $totalTasks > 0 ? (int)round(($completed / $totalTasks) * 100) : 0,
            'inProgressTasks' => $inProgress,
            'completedTasks' => $completed,
        ];
    }

    private function getRecentActivitiesForProjects(array $projects, int $limit = 10): array
    {
        // Simplification: si votre ActivityRepo ne fournit pas par projets, prenez les dernières globales
        $activities = $this->activityRepository->findBy([], ['dateCreation' => 'DESC'], $limit);
        return array_map(function ($a) {
            return [
                'type' => (string) $a->getType()->value ?? (string) $a->getType(),
                'description' => $a->getAction(),
                'dateCreation' => $a->getDateCreation()?->format(DATE_ATOM),
                'user' => $a->getUser() ? ($a->getUser()->getPrenom() . ' ' . $a->getUser()->getNom()) : null,
            ];
        }, $activities);
    }
}
