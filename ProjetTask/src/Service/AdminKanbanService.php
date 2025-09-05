<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskList;
use App\Entity\User;
use App\Enum\TaskStatut;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\TaskListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AdminKanbanService

{
     public function __construct(
        private ProjectRepository $projectRepository,
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private TaskListRepository $taskListRepository,
        private ActivityRepository $activityRepository,
        private EntityManagerInterface $entityManager,
        private KanbanService $kanbanService,
        private ActivityLogger $activityLogger,
        private NotificationService $notificationService,
        private Security $security,
        private PaginatorInterface $paginator
    ) {}

    /**
 * Returns a list of overdue tasks.
 */
public function getOverdueTasks(): array
{
    // Assuming you have access to TaskRepository via DI or service locator
    // Replace $this->taskRepository with your actual repository instance
    return $this->taskRepository->findTasksOverdue();
}

    /**
     * Returns recent activities for analytics dashboard.
     */
    public function getRecentActivities(): array
    {
        // Example implementation, replace with actual logic as needed
        return [];
    }

    /**
     * Perform a global search for tasks, projects, or users matching the query.
     *
     * @param string $query
     * @return array
     */
    public function globalSearch(string $query): array
    {
        // Example implementation, adapt as needed
        // Search tasks by title
        $tasks = $this->taskRepository->findByTitleLike($query);

        // Search projects by title
        $projects = $this->projectRepository->findByTitleLike($query);

        // Search users by name
        $users = $this->userRepository->findByNameLike($query);

        return [
            'tasks' => $tasks,
            'projects' => $projects,
            'users' => $users
        ];
    }

    /**
 * Returns workload distribution data for admin kanban.
 */
public function getWorkloadDistribution(): array
{
    // Example implementation, adjust as needed
    // You may want to fetch tasks and group by assigned user, etc.
    $tasks = $this->taskRepository->findAll();
    $distribution = [];

    foreach ($tasks as $task) {
        foreach ($task->getTaskUsers() as $taskUser) {
            $user = $taskUser->getUser();
            $userId = $user->getId();
            if (!isset($distribution[$userId])) {
                $distribution[$userId] = [
                    'user' => $user,
                    'taskCount' => 0
                ];
            }
            $distribution[$userId]['taskCount']++;
        }
    }

    return array_values($distribution);
}

     /**
     * Returns performance metrics for admin kanban dashboard.
     */
    public function getPerformanceMetrics(): array
    {
        // Example implementation, replace with actual logic as needed
        return [
            'tasksCompleted' => 0,
            'tasksInProgress' => 0,
            'averageCompletionTime' => 0,
        ];
    }

    /**
     * Create a quick task from provided data.
     */
    public function createQuickTask(array $data): array
    {
        // Implement your logic to create a quick task here.
        // Example stub:
        // $task = new Task();
        // $task->setTitle($data['title'] ?? 'Quick Task');
        // ... set other properties ...
        // $this->entityManager->persist($task);
        // $this->entityManager->flush();

        // Return a result array (customize as needed)
        return [
            'success' => true,
            'message' => 'Quick task created successfully',
            // 'taskId' => $task->getId(),
        ];
    }

    /**
     * Déplace une tâche dans une nouvelle liste et position.
     *
     * @param int $taskId
     * @param int $newListId
     * @param int $newPosition
     * @return bool
     */
    public function moveTask(int $taskId, int $newListId, int $newPosition): bool
    {
        // Implémentation de la logique de déplacement de tâche
        // Exemple basique, à adapter selon votre modèle
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
            // Optionnel : logger l'erreur
            return false;
        }
    }

    /**
     * Retourne les statistiques globales pour le dashboard admin.
     */
    public function getGlobalStatistics(): array
    {
        // Exemple de statistiques, à adapter selon vos besoins
        return [
            'total_projects' => 0,
            'total_tasks' => 0,
            'total_users' => 0,
            // Ajoutez d'autres statistiques ici
        ];
    }

    /**
     * Format a Task entity for response.
     */
    private function formatTaskForResponse(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'statut' => $task->getStatut(),
            'priority' => $task->getPriorite(),
            'deadline' => $task->getDeadline()?->format('Y-m-d H:i:s'),
            'position' => $task->getPosition(),
            'project' => [
                'id' => $task->getTaskList()->getProject()->getId(),
                'name' => $task->getTaskList()->getProject()->getTitre()
            ],
            'taskList' => [
                'id' => $task->getTaskList()->getId(),
                'name' => $task->getTaskList()->getNom()
            ],
            'assignedUser' => $task->getAssignedUser() ? $this->formatUserForResponse($task->getAssignedUser()) : null
        ];
    }

    /**
     * Format a Project entity for response.
     */
    private function formatProjectForResponse(Project $project): array
    {
        return [
            'id' => $project->getId(),
            'titre' => $project->getTitre(),
            'description' => $project->getDescription(),
            'statut' => $project->getStatut(),
            'chefProjet' => $project->getChefproject() ?
                $this->formatUserForResponse($project->getChefproject()) : null,
            'membresCount' => $project->getMembres()->count()
        ];
    }

    /**
     * Format a User entity for response.
     */
    private function formatUserForResponse(User $user): array
    {
        return [
            'id' => $user->getId(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'statut' => $user->getStatut(),
            'avatar' => $user->getAvatar() ?? null
        ];
    }

    /**
     * 🕑 Récupérer les activités récentes pour un utilisateur (employé)
     */
    private function getRecentActivitiesForUser(User $user, int $limit = 10): array
    {
        // Suppose que le repository a une méthode pour cela, sinon à implémenter
        if (method_exists($this->activityRepository, 'findRecentByUser')) {
            return $this->activityRepository->findRecentByUser($user, $limit);
        }
        // Fallback: récupérer les projets de l'utilisateur et utiliser la méthode existante
        $projects = $this->projectRepository->findByMembre($user);
        return $this->getRecentActivitiesForProjects($projects, $limit);
    }

    /**
     * 🕑 Récupérer les activités récentes pour une liste de projets
     */
    private function getRecentActivitiesForProjects(array $projects, int $limit = 10): array
    {
        $projectIds = array_map(fn($project) => $project->getId(), $projects);
        if (empty($projectIds)) {
            return [];
        }
        $activities = $this->activityRepository->findRecentByProjectIds($projectIds, $limit);
        // Vous pouvez formater les activités ici si besoin
        return $activities;
    }

    /**
     * Calcule les statistiques globales pour les projets et tâches donnés
     */
    private function calculateStatistics(array $projects, array $tasks): array
    {
        $totalProjects = count($projects);
        $totalTasks = count($tasks);
        $completedTasks = 0;
        $inProgressTasks = 0;
        $overdueTasks = 0;

        foreach ($tasks as $task) {
            if ($task->getStatut() === 'TERMINER') {
                $completedTasks++;
            } elseif ($task->getStatut() === 'EN_COURS') {
                $inProgressTasks++;
            }
            if ($task->getDeadline() && $task->getDeadline() < new \DateTime() && $task->getStatut() !== 'TERMINER') {
                $overdueTasks++;
            }
        }

        return [
            'totalProjects' => $totalProjects,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'inProgressTasks' => $inProgressTasks,
            'overdueTasks' => $overdueTasks,
            'completionRate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0
        ];
    }

    /**
     * Filtre les tâches selon les filtres fournis (statut, priorité, etc.)
     */
    private function applyFilters(array $tasks, array $filters = []): array
    {
        return array_filter($tasks, function ($task) use ($filters) {
            if (isset($filters['statut']) && $task->getStatut() !== $filters['statut']) {
                return false;
            }
            if (isset($filters['priority']) && $task->getPriority() !== $filters['priority']) {
                return false;
            }
            if (isset($filters['assignedUser']) && method_exists($task, 'getAssignedUser')) {
                $assignedUser = $task->getAssignedUser();
                if (!$assignedUser || $assignedUser->getId() !== $filters['assignedUser']) {
                    return false;
                }
            }
            // Ajoutez d'autres filtres ici si nécessaire
            return true;
        });
    }
    public function getAllKanbanDatas(): array
{
    // Replace the following with actual logic to fetch projects, tasks, users, taskLists, and statistics
    return [
        'projects' => [],      // Fetch projects from repository
        'tasks' => [],         // Fetch tasks from repository
        'users' => [],         // Fetch users from repository
        'taskLists' => [],     // Fetch task lists from repository
        'statistics' => []     // Compute statistics as needed
    ];
}
    /**
     * 📊 Données Kanban pour Admin et Directeur (accès total)
     */
    private function getAllKanbanData(array $filters = []): array
    {
        $projects = $this->projectRepository->findAll();
        $tasks = [];
        $taskLists = [];

        foreach ($projects as $project) {
            $projectTasks = $this->taskRepository->findByProject($project);
            $projectTaskLists = $this->taskListRepository->findByProjectWithTasksOrdered($project);

            $tasks = array_merge($tasks, $projectTasks);
            $taskLists = array_merge($taskLists, $projectTaskLists);
        }

        $users = $this->userRepository->findActiveUsers();

        return [
            'projects' => $projects,
            'tasks' => $this->
                applyFilters($tasks, $filters),
            'users' => $users,
            'taskLists' => $taskLists,
            'statistics' => $this->calculateStatistics($projects, $tasks),
            'recentActivities' => $this->getRecentActivitiesForProjects($projects),
            'userRole' => 'ADMIN'
        ];
    }

   

    /**  
     * 🎯 NOUVELLE MÉTHODE - Récupère les données selon les droits de l'utilisateur  
     */
    public function getKanbanDataByRole(User $user, array $filters = []): array
    {
        $userRoles = $user->getRoles();

        // Admin et Directeur : Accès total  
        if (in_array('ROLE_ADMIN', $userRoles) || in_array('ROLE_DIRECTEUR', $userRoles)) {
            return $this->getAllKanbanData($filters);
        }

        // Chef de projet : Ses projets uniquement  
        if (in_array('ROLE_CHEF_PROJET', $userRoles)) {
            return $this->getChefProjetKanbanData($user, $filters);
        }

        // Employé : Projets où il est membre + ses tâches  
        if (in_array('ROLE_EMPLOYE', $userRoles)) {
            return $this->getEmployeKanbanData($user, $filters);
        }

        return ['projects' => [], 'tasks' => [], 'users' => [], 'taskLists' => []];
    }

    /**  
     * 📊 Données Kanban pour Chef de Projet  
     */
    private function getChefProjetKanbanData(User $chefProjet, array $filters = []): array
    {
        // Projets gérés par le chef  
        $managedProjects = $this->projectRepository->findByChefDeproject($chefProjet);

        // Projets où il est membre  
        $memberProjects = $this->projectRepository->findByMembre($chefProjet);

        // Fusionner et dédoublonner  
        $allProjects = array_unique(array_merge($managedProjects, $memberProjects), SORT_REGULAR);

        $tasks = [];
        $taskLists = [];

        foreach ($allProjects as $project) {
            $projectTasks = $this->taskRepository->findByProject($project);
            $projectTaskLists = $this->taskListRepository->findByProjectWithTasksOrdered($project);

            $tasks = array_merge($tasks, $projectTasks);
            $taskLists = array_merge($taskLists, $projectTaskLists);
        }

        // Utilisateurs des projets gérés  
        $users = $this->getUsersFromProjects($managedProjects);

        return [
            'projects' => $allProjects,
            'tasks' => $this->applyFilters($tasks, $filters),
            'users' => $users,
            'taskLists' => $taskLists,
            'statistics' => $this->calculateStatistics($allProjects, $tasks),
            'recentActivities' => $this->getRecentActivitiesForProjects($allProjects),
            'userRole' => 'CHEF_PROJET',
            'managedProjects' => $managedProjects  // Projets où il peut assigner  
        ];
    }

    /**
     * 👥 Récupérer tous les utilisateurs des projets donnés
     */
    private function getUsersFromProjects(array $projects): array
    {
        $users = [];
        foreach ($projects as $project) {
            $projectMembers = $project->getMembres()->toArray();
            $users = array_merge($users, $projectMembers);

            // Ajouter le chef de projet
            if ($project->getChefproject()) {
                $users[] = $project->getChefproject();
            }
        }

        return array_unique($users, SORT_REGULAR);
    }

    /**  
     * 👨‍💼 Données Kanban pour Employé  
     */
    private function getEmployeKanbanData(User $employe, array $filters = []): array
    {
        // Projets où l'employé est membre  
        $projects = $this->projectRepository->findByMembre($employe);

        // Tâches assignées à l'employé  
        $assignedTasks = $this->taskRepository->findByAssignedUser($employe);

        // Toutes les tâches des projets (pour contexte)  
        $allProjectTasks = [];
        $taskLists = [];

        foreach ($projects as $project) {
            $projectTasks = $this->taskRepository->findByProject($project);
            $projectTaskLists = $this->taskListRepository->findByProjectWithTasksOrdered($project);

            $allProjectTasks = array_merge($allProjectTasks, $projectTasks);
            $taskLists = array_merge($taskLists, $projectTaskLists);
        }

        // Utilisateurs des projets (équipe)  
        $users = $this->getUsersFromProjects($projects);

        return [
            'projects' => $projects,
            'tasks' => $this->applyFilters($allProjectTasks, $filters),
            'assignedTasks' => $assignedTasks, // Tâches spécifiques à l'employé  
            'users' => $users,
            'taskLists' => $taskLists,
            'statistics' => $this->calculateEmployeStatistics($employe, $assignedTasks),
            'recentActivities' => $this->getRecentActivitiesForUser($employe),
            'userRole' => 'EMPLOYE'
        ];
    }

    /**
     * 📊 Statistiques spécifiques pour employé
     */
    private function calculateEmployeStatistics(User $employe, array $assignedTasks): array
    {
        $completedTasks = array_filter($assignedTasks, fn($t) => $t->getStatut() === 'TERMINER');
        $overdueTasks = array_filter($assignedTasks, function ($t) {
            return $t->getDeadline() &&
                $t->getDeadline() < new \DateTime() &&
                $t->getStatut() !== 'TERMINER';
        });

        return [
            'totalAssignedTasks' => count($assignedTasks),
            'totalCompletedTasks' => count($completedTasks),
            'completedTasks' => count($completedTasks),
            'inProgressTasks' => count(array_filter($assignedTasks, fn($t) => $t->getStatut() === 'EN_COURS')),
            'overdueTasks' => count($overdueTasks),
            'completionRate' => count($assignedTasks) > 0 ?
                round((count($completedTasks) / count($assignedTasks)) * 100, 1) : 0,
            'efficiency' => $this->calculateUserEfficiency($employe)
        ];
    }

    /**
     * 📊 Calcule l'efficacité d'un utilisateur basé sur les tâches terminées à temps
     */
    private function calculateUserEfficiency(User $user): float
    {
        $tasks = $this->taskRepository->findByAssignedUser($user);
        if (empty($tasks)) {
            return 0.0;
        }

        $onTimeCompletions = 0;
        $totalCompleted = 0;

        foreach ($tasks as $task) {
            if ($task->getStatut() === 'TERMINER') {
                $totalCompleted++;
                if ($task->getDeadline() && $task->getDeadline() >= $task->getUpdatedAt()) {
                    $onTimeCompletions++;
                }
            }
        }

        return $totalCompleted > 0 ? round(($onTimeCompletions / $totalCompleted) * 100, 1) : 0.0;
    }

    /**  
     * 🎯 NOUVELLE MÉTHODE - Assigner un utilisateur à un projet  
     */
    public function assignUserToProject(int $userId, int $projectId, User $assignedBy): array
    {
        try {
            $user = $this->userRepository->find($userId);
            $project = $this->projectRepository->find($projectId);

            if (!$user || !$project) {
                return ['success' => false, 'message' => 'Utilisateur ou projet introuvable'];
            }

            // Vérifier les droits d'assignation  
            if (!$this->canAssignToProject($assignedBy, $project)) {
                return ['success' => false, 'message' => 'Droits insuffisants pour cette assignation'];
            }

            // Vérifier si déjà membre  
            if ($project->getMembres()->contains($user)) {
                return ['success' => false, 'message' => 'Utilisateur déjà membre du projet'];
            }

            // Assigner  
            $project->addMembre($user);
            $this->entityManager->flush();

            // Log de l'activité  
            $this->activityLogger->logProjectAssignment($user, $project, $assignedBy);

            // Notification  
            $this->notificationService->createProjectAssignmentNotification($project, $user, $assignedBy);

            return [
                'success' => true,
                'message' => 'Utilisateur assigné au projet avec succès',
                'user' => $this->formatUserForResponse($user),
                'project' => $this->formatProjectForResponse($project)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de l\'assignation: ' . $e->getMessage()];
        }
    }

    /**  
     * 🎯 NOUVELLE MÉTHODE - Assigner un utilisateur à une tâche  
     */
    public function assignUserToTask(int $userId, int $taskId, User $assignedBy): array
    {
        try {
            $user = $this->userRepository->find($userId);
            $task = $this->taskRepository->find($taskId);

            if (!$user || !$task) {
                return ['success' => false, 'message' => 'Utilisateur ou tâche introuvable'];
            }

            $project = $task->getTaskList()->getProject();

            // Vérifier les droits d'assignation  
            if (!$this->canAssignToTask($assignedBy, $task)) {
                return ['success' => false, 'message' => 'Droits insuffisants pour cette assignation'];
            }

            // Vérifier si l'utilisateur est membre du projet  
            if (!$project->getMembres()->contains($user) && $project->getChefproject() !== $user) {
                return ['success' => false, 'message' => 'L\'utilisateur doit être membre du projet'];
            }

            // Assigner (selon votre modèle de données)  
            if (method_exists($task, 'setAssignedUser')) {
                $task->setAssignedUser($user);
            }
            // Ou si vous utilisez TaskUser  
            if (method_exists($task, 'addTaskUser')) {
                // Créer une relation TaskUser si nécessaire  
            }

            $this->entityManager->flush();

// Log de l'activité
$this->activityLogger->logTaskCreation($assignedBy, $task->getTitle(), $task->getId(), $task->getTaskList()->getProject());

// Notification
$this->notificationService->notifyTaskAssignment($task, $task->getAssignedUser());

return [
    'success' => true,
    'message' => 'Utilisateur assigné à la tâche avec succès',
    'user' => $this->formatUserForResponse($user),
    'task' => $this->formatTaskForResponse($task)
];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de l\'assignation: ' . $e->getMessage()];
        }
    }
    
    /**  
     * 🔐 Vérifier si un utilisateur peut assigner à un projet  
     */
    private function canAssignToProject(User $user, Project $project): bool
    {
        $roles = $user->getRoles();

        // Admin et Directeur peuvent assigner partout  
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
            return true;
        }

        // Chef de projet peut assigner sur ses projets  
        if (in_array('ROLE_CHEF_PROJET', $roles) && $project->getChefproject() === $user) {
            return true;
        }

        return false;
    }

    /**  
     * 🔐 Vérifier si un utilisateur peut assigner à une tâche  
     */
    private function canAssignToTask(User $user, Task $task): bool
    {
        $roles = $user->getRoles();
        $project = $task->getTaskList()->getProject();

        // Admin et Directeur peuvent assigner partout  
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
            return true;
        }

        // Chef de projet peut assigner sur les tâches de ses projets  
        if (in_array('ROLE_CHEF_PROJET', $roles) && $project->getChefproject() === $user) {
            return true;
        }

        return false;
    }

    /**  
     * 🎯 NOUVELLE MÉTHODE - Promouvoir un utilisateur en chef de projet  
     */
    public function promoteToChefProjet(int $userId, int $projectId, User $promotedBy): array
    {
        try {
            $user = $this->userRepository->find($userId);
            $project = $this->projectRepository->find($projectId);

            if (!$user || !$project) {
                return ['success' => false, 'message' => 'Utilisateur ou projet introuvable'];
            }

            // Vérifier si l'utilisateur a les droits pour promouvoir
            // if (!$this->canPromoteToChefProjet($promotedBy, $project)) {
            //     return ['success' => false, 'message' => 'Droits insuffisants pour promouvoir'];
            // }

            // Vérifier si l'utilisateur est déjà chef de projet
            if ($project->getChefproject() === $user) {
                return ['success' => false, 'message' => 'Utilisateur déjà chef de projet'];
            }

            // Promouvoir l'utilisateur à chef de projet
            $project->setChefproject($user);
            $this->entityManager->flush();

            // Log de l'activité
            $this->activityLogger->logChefProjetPromotion($promotedBy, $user, $project, $promotedBy);

            return [
                'success' => true,
                'message' => 'Utilisateur promu chef de projet avec succès',
                'user' => $this->formatUserForResponse($user),
                'project' => $this->formatProjectForResponse($project)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la promotion: ' . $e->getMessage()];
        }
    }

    /**
     * 📋 Récupérer la liste des utilisateurs assignables
     */
public function getAssignableUsersForProject(User $currentUser, ?Project $project = null): array
{
    $currentUserRoles = $currentUser->getRoles();

    // Admin et Directeur voient tous les utilisateurs
    if (in_array('ROLE_ADMIN', $currentUserRoles) || in_array('ROLE_DIRECTEUR', $currentUserRoles)) {
        return $this->userRepository->findActiveUsers();
    }

    // Chef de projet voit les membres de ses projets + utilisateurs assignables
    if (in_array('ROLE_CHEF_PROJET', $currentUserRoles)) {
        if ($project && $project->getChefproject() === $currentUser) {
            // Membres actuels + utilisateurs disponibles
            $projectMembers = $project->getMembres()->toArray();
            $availableUsers = $this->userRepository->findAvailableForProject($project);

            return array_unique(array_merge($projectMembers, $availableUsers), SORT_REGULAR);
        }
        // Seulement les membres de ses projets
        // Si le projet n'est pas fourni ou l'utilisateur n'est pas chef du projet, retourner les membres des projets qu'il gère
        // Si la méthode getUsersFromManagedProjects existe, décommentez la ligne suivante :
        // return $this->getUsersFromManagedProjects($currentUser);
        // Sinon, retournez un tableau vide
        return [];
    }

    // Employé ne peut assigner personne
    return [];
}


    /**
     * 🔄 Déplacer une tâche avec vérification des droits
     */

    // public function moveTaskWithRoleCheck(int $taskId, int $newListId, int $newPosition, User $user): array

    // {

    //     try {
    //         $task = $this->taskRepository->find($taskId);
    //         $newList = $this->taskListRepository->find($newListId);

    //         if (!$task || !$newList) {
    //             return ['success' => false, 'message' => 'Tâche ou liste introuvable'];
    //         }

    //         $oldProject = $task->getTaskList()->getProject();
    //         $newProject = $newList->getProject();

    //         // Vérifier les droits selon le rôle
    //         if (!$this->canMoveTask($user, $task, $newList)) {
    //             return ['success' => false, 'message' => 'Droits insuffisants pour ce déplacement'];
    //         }

    //         // Utiliser le service Kanban existant
    //         $this->kanbanService->moveTask($task, $newListId, $newPosition);

    //     //     // Log spécifique selon le changement de projet
    //     //     if ($oldProject->getId() !== $newProject->getId()) {
    //     //         $this->activityLogger->logTaskTransfer(
    //     //             $task,
    //     //             $user,
    //     //             $oldProject,
    //     //             $newProject
    //     //         );

    //             // Notification aux chefs de projets concernés
    //             $this->notificationService->createTaskTransferNotification(
    //                 $task,
    //                 $oldProject,
    //                 $newProject,
    //                 $user
    //             );

    //         // }

        //     $this->entityManager->flush();

        //     return [
        //         'success' => true,
        //         'message' => 'Tâche déplacée avec succès',
        //         'task' => $this->formatTaskForResponse($task),
        //         'crossProject' => $oldProject->getId() !== $newProject->getId()
        //     ];
        // } catch (\Exception $e) {
        //     return [
        //         'success' => false,
        //         'message' => 'Erreur lors du déplacement: ' . $e->getMessage()
        //     ];
        // }
    // }
    /**
     * 🔐 Vérifier si un utilisateur peut déplacer une tâche
     */
    // private function canMoveTask(User $user, Task $task, TaskList $targetList): bool
    // {
    //     $roles = $user->getRoles();
    //     $currentProject = $task->getTaskList()->getProject();
    //     $targetProject = $targetList->getProject();

    //     // Admin et Directeur peuvent tout déplacer
    //     if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
    //         return true;
    //     }
    }

    //     // Chef de projet peut déplacer dans ses projets
    //     if (in_array('ROLE_CHEF_PROJET', $roles)) {
    //         return ($currentProject->getChefproject() === $user) ||
    //             ($targetProject->getChefproject() === $user);
    //     }

    //     // Employé peut déplacer ses propres tâches dans le même projet
    //     if (in_array('ROLE_EMPLOYE', $roles)) {
    //         // Vérifier si c'est sa tâche et même projet
    //         $isAssigned = $this->isTaskAssignedToUser($task, $user);
    //         $sameProject = $currentProject->getId() === $targetProject->getId();

    //         return $isAssigned && $sameProject;
    //     }
    // }

    /**
     * 📊 Statistiques spécifiques pour employé
     */
    // private function calculateEmployeStatistics(User $employe, array $assignedTasks): array
    // {
    //     $completedTasks = array_filter($assignedTasks, fn($t) => $t->getStatut() === 'TERMINER');
    //     $overdueTasks = array_filter($assignedTasks, function ($t) {
    //         return $t->getDeadline() &&
    //             $t->getDeadline() < new \DateTime() &&
    //             $t->getStatut() !== 'TERMINER';
    //     });

    //     return [
    //         'totalAssignedTasks' => count($assignedTasks),
    //         'totalCompletedTasks' => count($completedTasks),
    //         'completedTasks' => count($completedTasks),
    //         'inProgressTasks' => count(array_filter($assignedTasks, fn($t) => $t->getStatut() === 'EN_COURS')),
    //         'overdueTasks' => count($overdueTasks),
    //         'completionRate' => count($assignedTasks) > 0 ?
    //             round((count($completedTasks) / count($assignedTasks)) * 100, 1) : 0,
    //         'efficiency' => $this->calculateUserEfficiency($employe)
    //     ];
    // }

    // /**
    //  * 👥 Récupérer les utilisateurs des projets gérés
    //  */
    // private function getUsersFromManagedProjects(User $chefProjet): array
    // {
    //     $managedProjects = $this->projectRepository->findByChefDeproject($chefProjet);
    //     return $this->getUsersFromProjects($managedProjects);
    // }

    // /**
    //  * 👥 Récupérer tous les utilisateurs des projets donnés
    //  */
    // private function getUsersFromProjects(array $projects): array
    // {
    //     $users = [];
    //     foreach ($projects as $project) {
    //         $projectMembers = $project->getMembres()->toArray();
    //         $users = array_merge($users, $projectMembers);

    //         // Ajouter le chef de projet
    //         if ($project->getChefproject()) {
    //             $users[] = $project->getChefproject();
    //         }
    //     }

    //     return array_unique($users, SORT_REGULAR);
    // }

    /**
     * 🔍 Vérifier si une tâche est assignée à un utilisateur
     */

//     private function isTaskAssignedToUser(Task $task, User $user): bool
//     {
//         // Selon votre modèle de données
//         foreach ($task->getTaskUsers() as $taskUser) {
//             if ($taskUser->getUser()->getId() === $user->getId()) {
//                 return true;
//             }
//         }
//         return false;
//     }

//     /**
//      * 📤 Formater les réponses
//      */
//     private function formatUserForResponse(User $user): array
//     {
//         return [
//             'id' => $user->getId(),
//             'nom' => $user->getNom(),
//             'prenom' => $user->getPrenom(),
//             'email' => $user->getEmail(),
//             'roles' => $user->getRoles(),
//             'statut' => $user->getStatut(),
//             'avatar' => $user->getAvatar() ?? null
//         ];
//     }

//     private function formatProjectForResponse(Project $project): array
//     {
//         return [
//             'id' => $project->getId(),
//             'titre' => $project->getTitre(),
//             'description' => $project->getDescription(),
//             'statut' => $project->getStatut(),
//             'chefProjet' => $project->getChefproject() ?
//                 $this->formatUserForResponse($project->getChefproject()) : null,
//             'membresCount' => $project->getMembres()->count()
//         ];
//     }

//     private function formatTaskForResponse(Task $task): array
//     {
//         return [
//             'id' => $task->getId(),
//             'title' => $task->getTitle(),
//             'description' => $task->getDescription(),
//             'statut' => $task->getStatut(),
//             'priority' => $task->getPriority(),
//             'deadline' => $task->getDeadline()?->format('Y-m-d H:i:s'),
//             'position' => $task->getPosition(),
//             'project' => [
//                 'id' => $task->getTaskList()->getProject()->getId(),
//                 'name' => $task->getTaskList()->getProject()->getTitre()
//             ],
//             'taskList' => [
//                 'id' => $task->getTaskList()->getId(),
//                 'name' => $task->getTaskList()->getLastName()
//             ],
//             'assignedUsers' => array_map(
//                 fn($tu) => $this->formatUserForResponse($tu->getUser()),
//                 $task->getTaskUsers()->toArray()
//             )
//         ];
//     }

//     // Méthodes utilitaires supplémentaires...

//     /**
//      * Filtre les tâches selon les filtres fournis (statut, priorité, etc.)
//      */
//     private function applyFilters(array $tasks, array $filters = []): array
//     {
//         return array_filter($tasks, function ($task) use ($filters) {
//             if (isset($filters['statut']) && $task->getStatut() !== $filters['statut']) {
//                 return false;
//             }
//             if (isset($filters['priority']) && $task->getPriority() !== $filters['priority']) {
//                 return false;
//             }
//             if (isset($filters['assignedUser']) && method_exists($task, 'getAssignedUser')) {
//                 $assignedUser = $task->getAssignedUser();
//                 if (!$assignedUser || $assignedUser->getId() !== $filters['assignedUser']) {
//                     return false;
//                 }
//             }
//             // Ajoutez d'autres filtres ici si nécessaire
//             return true;
//         });
//     }

//     public function getProjectsByRole(User $user): array
//     {
//         $roles = $user->getRoles();

//         if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
//             return $this->projectRepository->findAll();
//         }

//         if (in_array('ROLE_CHEF_PROJET', $roles)) {
//             $managed = $this->projectRepository->findByChefDeproject($user);
//             $member = $this->projectRepository->findByMembre($user);
//             return array_unique(array_merge($managed, $member), SORT_REGULAR);
//         }

//         return $this->projectRepository->findByMembre($user);
//     }

//     /**
//      * 📊 Calcule les statistiques globales pour les projets et tâches donnés
//      */
//     private function calculateStatistics(array $projects, array $tasks): array
//     {
//         $totalProjects = count($projects);
//         $totalTasks = count($tasks);
//         $completedTasks = 0;
//         $inProgressTasks = 0;
//         $overdueTasks = 0;

//         foreach ($tasks as $task) {
//             if ($task->getStatut() === 'TERMINER') {
//                 $completedTasks++;
//             } elseif ($task->getStatut() === 'EN_COURS') {
//                 $inProgressTasks++;
//             }
//             if ($task->getDeadline() && $task->getDeadline() < new \DateTime() && $task->getStatut() !== 'TERMINER') {
//                 $overdueTasks++;
//             }
//         }

//         return [
//             'totalProjects' => $totalProjects,
//             'totalTasks' => $totalTasks,
//             'completedTasks' => $completedTasks,
//             'inProgressTasks' => $inProgressTasks,
//             'overdueTasks' => $overdueTasks,
//             'completionRate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0
//         ];
//     }

//     /**
//      * 🕑 Récupérer les activités récentes pour une liste de projets
//      */
//     private function getRecentActivitiesForProjects(array $projects, int $limit = 10): array
//     {
//         $projectIds = array_map(fn($project) => $project->getId(), $projects);
//         if (empty($projectIds)) {
//             return [];
//         }
//         $activities = $this->activityRepository->findRecentByProjectIds($projectIds, $limit);
//         // Vous pouvez formater les activités ici si besoin
//         return $activities;
//     }

//     /**
//      * 🕑 Récupérer les activités récentes pour un utilisateur (employé)
//      */
//     private function getRecentActivitiesForUser(User $user, int $limit = 10): array
//     {
//         // Suppose que le repository a une méthode pour cela, sinon à implémenter
//         if (method_exists($this->activityRepository, 'findRecentByUser')) {
//             return $this->activityRepository->findRecentByUser($user, $limit);
//         }
//         // Fallback: récupérer les projets de l'utilisateur et utiliser la méthode existante
//         $projects = $this->projectRepository->findByMembre($user);
//         return $this->getRecentActivitiesForProjects($projects, $limit);
//     }
// }

//     /**
//      * 📊 Calcule l'efficacité d'un utilisateur basé sur les tâches terminées à temps
//      */
//     private function calculateUserEfficiency(User $user): float
//     {
//         $tasks = $this->taskRepository->findByAssignedUser($user);
//         if (empty($tasks)) {
//             return 0.0;
//         }

//         $onTimeCompletions = 0;
//         $totalCompleted = 0;

//         foreach ($tasks as $task) {
//             if ($task->getStatut() === 'TERMINER') {
//                 $totalCompleted++;
//                 if ($task->getDeadline() && $task->getDeadline() >= $task->getUpdatedAt()) {
//                     $onTimeCompletions++;
//                 }
//             }
//         }

//         return $totalCompleted > 0 ? round(($onTimeCompletions / $totalCompleted) * 100, 1) : 0.0;
//     }

//     /**
//      * 📊 Calcule le taux de remplissage d'une liste de projets
//      */
//     private function calculateProjectFillRate(array $projects): float
//     {
//         $totalTasks = 0;
//         $completedTasks = 0;

//         foreach ($projects as $project) {
//             $totalTasks += $project->getTaskLists()->count();
//             $completedTasks += count($project->getTasks()->filter(fn($task) => $task->getStatut() === 'TERMINER'));
//         }

//         return $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0.0;
//     }
// }
//         $this->kanbanService->moveTask($task, $newList, $newPosition);

//             // Log spécifique selon le changement de projet
//             if ($oldProject->getId() !== $newProject->getId()) {
//                 $this->activityLogger->logTaskTransfer(
//                     $task,
//                     $user,
//                     $oldProject,
//                     $newProject
//                 );

//                 // Notification aux chefs de projets concernés
//                 $this->notificationService->createTaskTransferNotification(
//                     $task,
//                     $oldProject,
//                     $newProject,
//                     $user
//                 );
//             } else {
//                 $this->activityLogger->logTaskMove(
//                     $task,
//                     $user,
//                     $task->getTaskList()->getLastName(),
//                     $newList->getLastName()
//                 );
//             }

//             $this->entityManager->flush();

//             return [
//                 'success' => true,
//                 'message' => 'Tâche déplacée avec succès',
//                 'task' => $this->formatTaskForResponse($task),
//                 'crossProject' => $oldProject->getId() !== $newProject->getId()
//             ];
//         } catch (\Exception $e) {
//             return [
//                 'success' => false,
//                 'message' => 'Erreur lors du déplacement: ' . $e->getMessage()
//             ];
//         }
//     }