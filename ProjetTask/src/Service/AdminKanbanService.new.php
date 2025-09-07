<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskList;
use App\Entity\User;
use App\Enum\TaskStatut;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskListRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Security\Core\Security;

class AdminKanbanService
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private TaskListRepository $taskListRepository,
        private ActivityRepository $activityRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private PaginatorInterface $paginator
    ) {}

    /**
     * Get Kanban data based on user role
     */
    public function getKanbanDataByRole(User $user, array $filters = []): array
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_DIRECTEUR', $roles, true)) {
            return $this->getAllKanbanData($filters);
        }

        if (in_array('ROLE_CHEF_PROJET', $roles, true)) {
            return $this->getChefProjetKanbanData($user, $filters);
        }

        return $this->getEmployeKanbanData($user, $filters);
    }

    /**
     * Get all Kanban data (admin access)
     */
    private function getAllKanbanData(array $filters = []): array
    {
        $projects = $this->projectRepository->findAll();
        $taskLists = $this->taskListRepository->findAll();
        $tasks = $this->taskRepository->findAll();
        
        $tasks = $this->applyFilters($tasks, $filters);
        $users = $this->userRepository->findAll();

        return [
            'projects' => $projects,
            'taskLists' => $taskLists,
            'tasks' => $tasks,
            'users' => $users,
            'statistics' => $this->calculateStatistics($projects, $tasks),
            'recentActivities' => $this->getRecentActivitiesForProjects($projects),
            'userRole' => 'ADMIN',
        ];
    }

    /**
     * Get Kanban data for Chef de Projet
     */
    private function getChefProjetKanbanData(User $user, array $filters = []): array
    {
        $managedProjects = $this->projectRepository->findBy(['chefProject' => $user]);
        $memberProjects = $this->projectRepository->findByMember($user);
        $projects = array_unique(array_merge($managedProjects, $memberProjects), SORT_REGULAR);

        $taskLists = [];
        $tasks = [];
        
        foreach ($projects as $project) {
            $projectTaskLists = $this->taskListRepository->findBy(['project' => $project]);
            $taskLists = array_merge($taskLists, $projectTaskLists);
            $tasks = array_merge($tasks, $this->taskRepository->findBy(['project' => $project]));
        }

        $tasks = $this->applyFilters($tasks, $filters);
        $users = $this->getUsersFromProjects($projects);

        return [
            'projects' => $projects,
            'taskLists' => $taskLists,
            'tasks' => $tasks,
            'users' => $users,
            'statistics' => $this->calculateStatistics($projects, $tasks),
            'recentActivities' => $this->getRecentActivitiesForProjects($projects),
            'userRole' => 'CHEF_PROJET',
        ];
    }

    /**
     * Get Kanban data for Employé
     */
    private function getEmployeKanbanData(User $user, array $filters = []): array
    {
        $projects = $this->projectRepository->findByMember($user);
        
        $taskLists = [];
        $tasks = [];
        
        foreach ($projects as $project) {
            $projectTaskLists = $this->taskListRepository->findBy(['project' => $project]);
            $taskLists = array_merge($taskLists, $projectTaskLists);
            
            $projectTasks = $this->taskRepository->createQueryBuilder('t')
                ->leftJoin('t.assignedUsers', 'au')
                ->where('t.project = :project')
                ->andWhere('t.assignedTo = :user OR au = :user')
                ->setParameter('project', $project)
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();
            
            $tasks = array_merge($tasks, $projectTasks);
        }

        $tasks = $this->applyFilters($tasks, $filters);
        $users = $this->getUsersFromProjects($projects);

        return [
            'projects' => $projects,
            'taskLists' => $taskLists,
            'tasks' => array_unique($tasks, SORT_REGULAR),
            'users' => $users,
            'statistics' => $this->calculateStatistics($projects, $tasks),
            'recentActivities' => $this->getRecentActivitiesForProjects($projects),
            'userRole' => 'EMPLOYE',
        ];
    }

    /**
     * Get all users from a list of projects
     */
    private function getUsersFromProjects(array $projects): array
    {
        $users = [];
        foreach ($projects as $project) {
            if (method_exists($project, 'getMembers')) {
                foreach ($project->getMembers() as $user) {
                    $users[$user->getId()] = $user;
                }
            }
            if (method_exists($project, 'getChefProject') && $project->getChefProject()) {
                $chef = $project->getChefProject();
                $users[$chef->getId()] = $chef;
            }
        }
        return array_values($users);
    }

    /**
     * Apply filters to tasks
     */
    private function applyFilters(array $tasks, array $filters): array
    {
        return array_filter($tasks, function ($task) use ($filters) {
            if (!empty($filters['project_id']) && 
                $task->getProject() && 
                (int)$filters['project_id'] !== $task->getProject()->getId()) {
                return false;
            }
            
            if (!empty($filters['assigned_user'])) {
                $assigned = $task->getAssignedTo();
                if (!$assigned || (int)$filters['assigned_user'] !== $assigned->getId()) {
                    return false;
                }
            }
            
            if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
                if (method_exists($task, 'getPriority') && 
                    $task->getPriority() !== $filters['priority']) {
                    return false;
                }
            }
            
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                if (method_exists($task, 'getStatus') && 
                    $task->getStatus() !== $filters['status']) {
                    return false;
                }
            }
            
            return true;
        });
    }

    /**
     * Calculate statistics for projects and tasks
     */
    private function calculateStatistics(array $projects, array $tasks): array
    {
        $completed = 0;
        $overdue = 0;
        $users = [];
        $now = new \DateTimeImmutable();
        $oneWeekAgo = $now->sub(new \DateInterval('P7D'));
        $completedThisWeek = 0;

        foreach ($tasks as $task) {
            if (method_exists($task, 'getStatus') && $task->getStatus() === 'TERMINER') {
                $completed++;
                
                if (method_exists($task, 'getUpdatedAt') && 
                    $task->getUpdatedAt() && 
                    $task->getUpdatedAt() > $oneWeekAgo) {
                    $completedThisWeek++;
                }
            }
            
            if (method_exists($task, 'getDueDate') && 
                $task->getDueDate() instanceof \DateTimeInterface && 
                $task->getDueDate() < $now && 
                method_exists($task, 'getStatus') && 
                $task->getStatus() !== 'TERMINER') {
                $overdue++;
            }
            
            if (method_exists($task, 'getAssignedTo') && $task->getAssignedTo()) {
                $users[$task->getAssignedTo()->getId()] = true;
            }
        }

        $totalTasks = count($tasks);
        $completionRate = $totalTasks > 0 ? (int)round(($completed / $totalTasks) * 100) : 0;
        $activeProjects = count(array_filter($projects, function($project) {
            return method_exists($project, 'isActive') ? $project->isActive() : true;
        }));

        return [
            'projectsTotal' => count($projects),
            'activeProjects' => $activeProjects,
            'completedTasks' => $completed,
            'completionRate' => $completionRate,
            'overdueTasks' => $overdue,
            'activeUsers' => count($users),
            'avgTasksPerUser' => count($users) > 0 ? round($totalTasks / count($users), 1) : 0,
            'completedThisWeek' => $completedThisWeek,
        ];
    }

    /**
     * Get recent activities for projects
     */
    public function getRecentActivitiesForProjects(array $projects, int $limit = 10): array
    {
        $projectIds = array_map(fn($project) => $project->getId(), $projects);
        
        $activities = $this->activityRepository->createQueryBuilder('a')
            ->where('a.project IN (:projects)')
            ->setParameter('projects', $projectIds)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(function($activity) {
            return [
                'type' => $activity->getType(),
                'user' => [
                    'id' => $activity->getUser()->getId(),
                    'fullName' => $activity->getUser()->getFullName(),
                    'avatar' => $activity->getUser()->getAvatar()
                ],
                'description' => $activity->getDescription(),
                'dateCreation' => $activity->getCreatedAt(),
                'project' => [
                    'id' => $activity->getProject()->getId(),
                    'title' => $activity->getProject()->getTitre()
                ]
            ];
        }, $activities);
    }

    /**
     * Get recent activities for admin dashboard
     */
    public function getRecentActivitiesForAdmin(): array
    {
        return $this->getRecentActivitiesForProjects($this->projectRepository->findAll());
    }

    /**
     * Get overdue tasks
     */
    public function getOverdueTasks(): array
    {
        return $this->taskRepository->findTasksOverdue();
    }

    /**
     * Get Kanban statistics
     */
    public function getKanbanStatistics(): array
    {
        $totalTasks = $this->taskRepository->count([]);
        $completedTasks = $this->taskRepository->count(['statut' => TaskStatut::TERMINER]);
        $inProgressTasks = $this->taskRepository->count(['statut' => TaskStatut::EN_COURS]);
        $notStartedTasks = $this->taskRepository->count(['statut' => TaskStatut::EN_ATTENTE]);
        $overdueTasks = count($this->getOverdueTasks());

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'in_progress_tasks' => $inProgressTasks,
            'not_started_tasks' => $notStartedTasks,
            'overdue_tasks' => $overdueTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
        ];
    }

    /**
     * Perform a global search
     */
    public function globalSearch(string $query): array
    {
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.title LIKE :query')
            ->orWhere('t.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->getQuery()
            ->getResult();

        $projects = $this->projectRepository->createQueryBuilder('p')
            ->where('p.titre LIKE :query')
            ->orWhere('p.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->getQuery()
            ->getResult();

        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.nom LIKE :query')
            ->orWhere('u.prenom LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->getQuery()
            ->getResult();

        return [
            'tasks' => array_map([$this, 'formatTaskForResponse'], $tasks),
            'projects' => array_map([$this, 'formatProjectForResponse'], $projects),
            'users' => array_map([$this, 'formatUserForResponse'], $users)
        ];
    }

    /**
     * Create a quick task
     */
    public function createQuickTask(array $data): array
    {
        try {
            $task = new Task();
            $task->setTitle($data['title'] ?? 'Nouvelle tâche rapide');
            $task->setDescription($data['description'] ?? '');
            $task->setStatus($data['status'] ?? TaskStatut::EN_ATTENTE);
            
            if (isset($data['project_id'])) {
                $project = $this->projectRepository->find($data['project_id']);
                if ($project) {
                    $task->setProject($project);
                }
            }

            if (isset($data['assigned_to'])) {
                $user = $this->userRepository->find($data['assigned_to']);
                if ($user) {
                    $task->setAssignedTo($user);
                }
            }

            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return [
                'success' => true,
                'message' => 'Tâche créée avec succès',
                'taskId' => $task->getId(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la tâche: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Move a task to a new list/position
     */
    public function moveTask(int $taskId, int $newListId, int $newPosition): bool
    {
        try {
            $task = $this->entityManager->getRepository(Task::class)->find($taskId);
            $newList = $this->entityManager->getRepository(TaskList::class)->find($newListId);

            if (!$task || !$newList) {
                return false;
            }

            $task->setTaskList($newList);
            $task->setPosition($newPosition);

            $this->entityManager->flush();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Format a Task entity for response
     */
    private function formatTaskForResponse(Task $task): array
    {
        $project = $task->getTaskList() ? $task->getTaskList()->getProject() : null;
        
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'deadline' => $task->getDueDate() ? $task->getDueDate()->format('Y-m-d H:i:s') : null,
            'createdAt' => $task->getCreatedAt() ? $task->getCreatedAt()->format('Y-m-d H:i:s') : null,
            'updatedAt' => $task->getUpdatedAt() ? $task->getUpdatedAt()->format('Y-m-d H:i:s') : null,
            'project' => $project ? [
                'id' => $project->getId(),
                'title' => $project->getTitre(),
                'slug' => $project->getSlug()
            ] : null,
            'taskList' => $task->getTaskList() ? [
                'id' => $task->getTaskList()->getId(),
                'name' => $task->getTaskList()->getNom(),
                'position' => $task->getTaskList()->getPosition()
            ] : null,
            'assignedTo' => $task->getAssignedTo() ? $this->formatUserForResponse($task->getAssignedTo()) : null,
        ];
    }

    /**
     * Format a User entity for response
     */
    private function formatUserForResponse(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'avatar' => $user->getAvatar(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive()
        ];
    }

    /**
     * Format a Project entity for response
     */
    private function formatProjectForResponse(Project $project): array
    {
        return [
            'id' => $project->getId(),
            'title' => $project->getTitre(),
            'slug' => $project->getSlug(),
            'description' => $project->getDescription(),
            'status' => $project->getStatut(),
            'startDate' => $project->getDateDebut() ? $project->getDateDebut()->format('Y-m-d') : null,
            'endDate' => $project->getDateFin() ? $project->getDateFin()->format('Y-m-d') : null,
            'createdAt' => $project->getCreatedAt() ? $project->getCreatedAt()->format('Y-m-d H:i:s') : null,
            'updatedAt' => $project->getUpdatedAt() ? $project->getUpdatedAt()->format('Y-m-d H:i:s') : null,
            'projectManager' => $project->getChefProject() ? $this->formatUserForResponse($project->getChefProject()) : null,
            'members' => array_map(
                fn($member) => $this->formatUserForResponse($member),
                $project->getMembers()->toArray()
            ),
            'taskLists' => array_map(
                fn($list) => [
                    'id' => $list->getId(),
                    'name' => $list->getNom(),
                    'position' => $list->getPosition(),
                    'taskCount' => $list->getTasks()->count()
                ],
                $project->getTaskLists()->toArray()
            )
        ];
    }
}
