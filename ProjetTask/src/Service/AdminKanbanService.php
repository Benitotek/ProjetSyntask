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
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\Security\Core\Security;

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
        private SecurityBundleSecurity $security,
        private EntityManagerInterface $entityManager
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
            'user' => $user,
            'currentUser' => $user
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
    public function moveTask(int $taskId, int $newListId, int $newPosition): bool
    {
        // Add logic to move the task to a new list and position
        // Example placeholder logic:
        try {
            // Fetch the task and update its list and position
            $task = $this->em->getRepository(Task::class)->find($taskId);
            $task->setListId($newListId);
            $task->setPosition($newPosition);
            $this->em->flush();

            return true; // Return true if successful
        } catch (\Exception $e) {
            // Log the error if needed
            return false; // Return false if an error occurs
        }
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

        // Journaliser7
        $this->activityLogger->logTaskstatutChange($by, $task->getTitle(), $task->getId(), $task->getTaskList(), $task->getStatut(), $task->getStatut(), $task->getTaskList());
        // Journaliser + notifier (optionnel/selon vos besoins)
        $this->notificationService->notifyTaskMoved($task, $by);

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
                $p = method_exists($t, 'getPriorite') ? $t->getPriorite() : (method_exists($t, 'getPriority') ? $t->getPriorite() : null);
                if ((string)$p !== (string)$filters['priority']) {
                    return false;
                }
            }
            // Filtre status
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $s = method_exists($t, 'getStatus') ? $t->getStatutLabel() : $t->getStatut();
                if ($this->normalizeStatus($s) !== (string)$filters['status']) {
                    return false;
                }
            }
            // Filtre date de création
            if (!empty($filters['date_creation'])) {
                $date = method_exists($t, 'getDateCreation') ? $t->getDateCreation() : $t->getDateCreation();
                if ($filters['date_creation'] === 'today' && $date->format('Y-m-d') !== (new \DateTime())->format('Y-m-d')) {
                    return false;
                }
                if ($filters['date_creation'] === 'this_week') {
                    $startOfWeek = (new \DateTime())->modify(('Monday' === (new \DateTime())->format('l')) ? 'this Monday' : 'last Monday')->setTime(0, 0);
                    $endOfWeek = (clone $startOfWeek)->modify('+6 days')->setTime(23, 59, 59);
                    if ($date < $startOfWeek || $date > $endOfWeek) {
                        return false;
                    }
                }
            }
            // Filtre date butoir
            if (!empty($filters['due_date'])) {
                $due = method_exists($t, 'getDueDate') ? $t->isOverdue() : $t->getDateButoir();
                $now = new \DateTimeImmutable();
                if ($filters['due_date'] === 'overdue' && (!($due instanceof \DateTimeInterface) || $due >= $now)) {
                    return false;
                }
                if ($filters['due_date'] === 'today' && (!($due instanceof \DateTimeInterface) || $due->format('Y-m-d') !== $now->format('Y-m-d'))) {
                    return false;
                }
                if ($filters['due_date'] === 'this_week') {
                    $startOfWeek = $now->modify(('Monday' === $now->format('l')) ? 'this Monday' : 'last Monday')->setTime(0, 0);
                    $endOfWeek = (clone $startOfWeek)->modify('+6 days')->setTime(23, 59, 59);
                    if (!($due instanceof \DateTimeInterface) || $due < $startOfWeek || $due > $endOfWeek) {
                        return false;
                    }
                }
            }
            return true;
        }));
    }
    public function getGlobalStatistics(): array
    {
        // Implementez ici la logique pour obtenir les statistiques globales
        // Par exemple, vous pouvez obtenir les projets, les tâches, et les utilisateurs
        // et les utiliser pour calculer les statistiques globales

        // Exemple de code :
        $projects = $this->projectRepository->findAll();
        $tasks = $this->taskRepository->findAll();
        $users = $this->userRepository->findAll();

        return $this->calculateStatistics($projects, $tasks, $users);
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
            if ($this->statusEquals($s, 'TERMINER')) {
                $completed++;
            }
            if ($this->statusEquals($s, 'EN_COURS')) {
                $inProgress++;
            }
            $due = method_exists($t, 'getDueDate') ? $t->getDueDate() : $t->getDateButoir();
            $sv = $this->normalizeStatus(method_exists($t, 'getStatus') ? $t->getStatus() : $t->getStatut());
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
    private function normalizeStatus(mixed $status): string
    {
        // Supporte: BackedEnum (->value), UnitEnum (->name) ou string direct
        if (is_object($status)) {
            if (method_exists($status, 'value')) {
                return (string) $status->value; // Backed enum
            }
            if (method_exists($status, 'name')) {
                return (string) $status->name; // Unit enum
            }
            // Dernier recours: éviter l'erreur en logguant le type
            return (string) ($status->__toString() ?? get_debug_type($status));
        }
        return (string) $status;
    }

    private function statusEquals(mixed $status, string $expected): bool
    {
        return $this->normalizeStatus($status) === $expected;
    }
    public function getAssignableUsers(User $user, ?Project $project = null): array
    {
        // Implementez ici la logique pour obtenir les utilisateurs assignables
        // Par exemple, vous pouvez obtenir les utilisateurs dont le role est "ROLE_USER"

        // Simplifié: admin voit tous les utilisateurs
        if ($user->getRoles()[0] === 'ROLE_ADMIN') {
            return $this->userRepository->findAll();
        }
        if ($project) {
            // Si un projet est fourni, filtrez les utilisateurs associés au projet
            return $this->userRepository->findByProject($project);
        }
        return $this->userRepository->findAll();
    }
    public function assignUserToProject(int $userId, int $projectId, $currentUser): array
    {
        // Implement the logic to assign a user to a project
        // Example logic:
        $project = $this->projectRepository->find($projectId);
        $user = $this->userRepository->find($userId);

        if (!$project || !$user) {
            throw new \InvalidArgumentException('Invalid project or user ID');
        }

        // Perform the assignment logic
        $project->addUser($user);
        $this->em->persist($project);
        $this->em->flush();

        return ['success' => true, 'message' => 'User assigned to project successfully'];
    }
    /**
     * Assign a user to a task.
     *
     * @param int $projectId
     * @param int $userId
     * @param int $taskId
     * @param bool $assignable
     * @param User $currentUser
     * @return array
     */
    public function assignUserToTask(int $projectId, int $userId, int $taskId, bool $assignable, $currentUser): array
    {
        // Implement the logic to assign a user to a task
        // Example: Validate permissions, update the database, and return the result
        return [
            'success' => true,
            'message' => 'User assigned to task successfully.'
        ];
    }
    public function promoteToChefProjet(int $userId, int $projectId, $currentUser): array
    {
        // Logic to promote a user to Chef de Projet
        // Example implementation:
        $user = $this->userRepository->find($userId);
        $project = $this->projectRepository->find($projectId);

        if (!$user || !$project) {
            throw new \InvalidArgumentException('Invalid user or project ID.');
        }

        // Check permissions and perform promotion
        if (!$this->security->isGranted('ROLE_DIRECTEUR', $currentUser)) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('Access denied.');
        }

        $user->setRole(\App\Enum\UserRole::CHEF_PROJET);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return ['success' => true, 'message' => 'User promoted to Chef de Projet.'];
    }
    public function getAllKanbanData(): array
    {
        // Implement logic to fetch all Kanban data
        return [
            'projects' => [], // Example data
            'tasks' => [],
            'users' => [],
            'taskLists' => [],
            'statistics' => [],
            'recentActivities' => [],
            'filters' => [],
            'user' => null,
            'currentUser' => null
        ];
    }
    public function getDirecteurKanbanData($user): array
    {
        // Implement logic to fetch Directeur-specific Kanban data
        return [
            'projects' => [], // Example data
            'tasks' => [],    // Example data
            'users' => [],    // Example data
            'taskLists' => [], // Example data
            'statistics' => [], // Example data
            'recentActivities' => [], // Example data
            'filters' => [],  // Example data
            'user' => $user,  // Pass the user object
            'currentUser' => $user // Pass the user object
        ];
    }
    public function getChefProjetKanbanData($user): array
    {
        // Implement logic to fetch Chef de Projet-specific Kanban data
        return [
            'projects' => [], // Example data
            'tasks' => [],    // Example data
            'users' => [],    // Example data
            'taskLists' => [], // Example data
            'statistics' => [], // Example data
            'recentActivities' => [], // Example data
            'filters' => [],  // Example data
            'user' => $user,  // Pass the user object
            'currentUser' => $user // Pass the user object
        ];
    }
    public function getEmployeKanbanData($user): array
    {
        // Implement logic to fetch User-specific Kanban data
        return [
            'projects' => [], // Example data
            'tasks' => [],    // Example data
            'users' => [],    // Example data
            'taskLists' => [], // Example data
            'statistics' => [], // Example data
            'recentActivities' => [], // Example data
            'filters' => [],  // Example data
            'user' => $user,  // Pass the user object
            'currentUser' => $user // Pass the user object
        ];
    }
    public function getUserKanbanData($user): array
    {
        // Implement logic to fetch User-specific Kanban data
        return [
            'projects' => [], // Example data
            'tasks' => [],    // Example data
            'users' => [],    // Example data
            'taskLists' => [], // Example data
            'statistics' => [], // Example data
            'recentActivities' => [], // Example data
            'filters' => [],  // Example data
            'user' => $user,  // Pass the user object
            'currentUser' => $user // Pass the user object
        ];
    }
}
