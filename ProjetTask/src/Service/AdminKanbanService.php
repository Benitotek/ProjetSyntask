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
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
     * Récupère les données Kanban en fonction du rôle de l'utilisateur
     * Get Kanban data based on user role
     * 
     * @param User $user L'utilisateur connecté / The logged-in user
     * @param array $filters Filtres à appliquer / Filters to apply
     * @return array Données formatées pour le Kanban / Formatted Kanban data
     */
    public function getKanbanDataByRole(User $user, array $filters = []): array
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_DIRECTEUR', $roles, true)) {
            return $this->getAdminKanbanData($filters);
        }

        if (in_array('ROLE_CHEF_PROJET', $roles, true)) {
            return $this->getChefProjetKanbanData($user, $filters);
        }

        return $this->getEmployeKanbanData($user, $filters);
    }

    /**
     * Récupère toutes les données Kanban (accès admin)
     * Get all Kanban data (admin access)
     * 
     * @param array $filters Filtres à appliquer / Filters to apply
     * @return array Données formatées / Formatted data
     */
    private function getAdminKanbanData(array $filters = []): array
    {
        // Récupérer tous les projets, listes de tâches et tâches
        $projects = $this->projectRepository->findAll();
        $taskLists = $this->taskListRepository->findAll();
        $tasks = $this->taskRepository->findAll();
        
        // Appliquer les filtres
        $tasks = $this->applyFilters($tasks, $filters);
        
        // Récupérer tous les utilisateurs
        $users = $this->userRepository->findAll();

        // Calculer les statistiques
        $statistics = $this->calculateStatistics($projects, $tasks);
        
        // Récupérer les activités récentes
        $recentActivities = $this->getRecentActivitiesForProjects($projects);

        return [
            'projects' => $projects,
            'taskLists' => $taskLists,
            'tasks' => $tasks,
            'users' => $users,
            'statistics' => $statistics,
            'recentActivities' => $recentActivities,
            'userRole' => 'ADMIN',
        ];
    }

    /**
     * Récupère les données Kanban pour un chef de projet
     * Get Kanban data for a project manager
     * 
     * @param User $user L'utilisateur connecté / The logged-in user
     * @param array $filters Filtres à appliquer / Filters to apply
     * @return array Données formatées / Formatted data
     */
    private function getChefProjetKanbanData(User $user, array $filters = []): array
    {
        // Récupérer les projets gérés par le chef de projet
        $projects = $this->projectRepository->findBy(['chefProject' => $user]);
        
        // Récupérer les listes de tâches et tâches des projets gérés
        $taskLists = [];
        $tasks = [];
        
        foreach ($projects as $project) {
            $projectTaskLists = $this->taskListRepository->findBy(['projet' => $project]);
            $taskLists = array_merge($taskLists, $projectTaskLists);
            
            foreach ($projectTaskLists as $taskList) {
                $listTasks = $this->taskRepository->findBy(['liste' => $taskList]);
                $tasks = array_merge($tasks, $listTasks);
            }
        }
        
        // Appliquer les filtres
        $tasks = $this->applyFilters($tasks, $filters);
        
        // Récupérer les utilisateurs des projets gérés
        $users = [];
        foreach ($projects as $project) {
            $projectUsers = $project->getMembres()->toArray();
            $users = array_merge($users, $projectUsers);
        }
        $users = array_unique($users, SORT_REGULAR);
        
        // Calculer les statistiques
        $statistics = $this->calculateStatistics($projects, $tasks);
        
        // Récupérer les activités récentes
        $recentActivities = $this->getRecentActivitiesForProjects($projects);

        return [
            'projects' => $projects,
            'taskLists' => $taskLists,
            'tasks' => $tasks,
            'users' => array_values($users),
            'statistics' => $statistics,
            'recentActivities' => $recentActivities,
            'userRole' => 'CHEF_PROJET',
        ];
    }


    /**
     * Récupère les utilisateurs à partir d'une liste de projets
     * Get users from a list of projects
     * 
     * @param array $projects Liste des projets / List of projects
     * @return array Utilisateurs uniques / Unique users
     */
    private function getUsersFromProjects(array $projects): array
    {
        $users = [];
        $userIds = [];

        foreach ($projects as $project) {
            if (method_exists($project, 'getMembres')) {
                foreach ($project->getMembres() as $user) {
                    if (!in_array($user->getId(), $userIds, true)) {
                        $userIds[] = $user->getId();
                        $users[] = $user;
                    }
                }
            }
        }

        return array_values($users);
    }

    /**
     * Applique les filtres aux tâches
     * Apply filters to tasks
     * 
     * @param array $tasks Liste des tâches à filtrer / Tasks to filter
     * @param array $filters Filtres à appliquer / Filters to apply
     * @return array Tâches filtrées / Filtered tasks
     */
    private function applyFilters(array $tasks, array $filters = []): array
    {
        if (empty($filters)) {
            return $tasks;
        }

        return array_values(array_filter($tasks, function ($task) use ($filters) {
            if (isset($filters['project_id']) && $filters['project_id'] !== 'all') {
                if (method_exists($task, 'getProjet') && $task->getProjet()?->getId() != $filters['project_id']) {
                    return false;
                }
            }

            if (isset($filters['status']) && $filters['status'] !== 'all') {
                if (method_exists($task, 'getStatut') && $task->getStatut() !== $filters['status']) {
                    return false;
                }
            }

            if (isset($filters['priority']) && $filters['priority'] !== 'all') {
                if (method_exists($task, 'getPriorite') && $task->getPriorite() !== $filters['priority']) {
                    return false;
                }
            }

            if (isset($filters['assignedUser']) && $filters['assignedUser'] !== 'all') {
                if (method_exists($task, 'getUtilisateurAssignation')) {
                    $assignedUser = $task->getUtilisateurAssignation();
                    if ($assignedUser === null || $assignedUser->getId() != $filters['assignedUser']) {
                        return false;
                    }
                } else {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Calcule les statistiques pour les projets et tâches
     * Calculate statistics for projects and tasks
     * 
     * @param array $projects Liste des projets / List of projects
     * @param array $tasks Liste des tâches / List of tasks
     * @return array Statistiques calculées / Calculated statistics
     */
    private function calculateStatistics(array $projects, array $tasks): array
    {
        $completed = 0;
        $overdue = 0;
        $users = [];
        $now = new \DateTimeImmutable();
        $oneWeekAgo = (new \DateTimeImmutable())->modify('-1 week');
        $completedThisWeek = 0;

        foreach ($tasks as $task) {
            // Vérification des tâches terminées
            if (method_exists($task, 'getStatut') && $task->getStatut() === 'TERMINER') {
                $completed++;
                
                // Vérification des tâches terminées cette semaine
                if (method_exists($task, 'getDateMiseAJour') && 
                    $task->getDateMiseAJour() instanceof \DateTimeInterface &&
                    $task->getDateMiseAJour() >= $oneWeekAgo) {
                    $completedThisWeek++;
                }
            }
            
            // Vérification des tâches en retard
            if (method_exists($task, 'getDateEcheance') && 
                $task->getDateEcheance() instanceof \DateTimeInterface && 
                $task->getDateEcheance() < $now && 
                method_exists($task, 'getStatut') && 
                $task->getStatut() !== 'TERMINER') {
                $overdue++;
            }
            
            // Suivi des utilisateurs uniques avec des tâches assignées
            if (method_exists($task, 'getUtilisateurAssignation') && $task->getUtilisateurAssignation()) {
                $users[$task->getUtilisateurAssignation()->getId()] = true;
            }
        }

        $totalTasks = count($tasks);
        $completionRate = $totalTasks > 0 ? (int)round(($completed / $totalTasks) * 100) : 0;
        $uniqueUsersCount = count($users);

        return [
            'projectsTotal' => count($projects),
            'activeProjects' => count($projects),
            'completedTasks' => $completed,
            'completionRate' => $completionRate,
            'overdueTasks' => $overdue,
            'activeUsers' => $uniqueUsersCount,
            'avgTasksPerUser' => $uniqueUsersCount > 0 ? round($totalTasks / $uniqueUsersCount, 1) : 0,
            'completedThisWeek' => $completedThisWeek,
            'not_started_tasks' => count(array_filter($tasks, fn($t) => method_exists($t, 'getStatut') && $t->getStatut() === 'A_FAIRE')),
            'in_progress_tasks' => count(array_filter($tasks, fn($t) => method_exists($t, 'getStatut') && $t->getStatut() === 'EN_COURS')),
            'in_review_tasks' => count(array_filter($tasks, fn($t) => method_exists($t, 'getStatut') && $t->getStatut() === 'EN_REVUE')),
            'completed_tasks' => $completed,
            'high_priority_tasks' => count(array_filter($tasks, fn($t) => method_exists($t, 'getPriorite') && $t->getPriorite() === 'HAUTE')),
            'medium_priority_tasks' => count(array_filter($tasks, fn($t) => method_exists($t, 'getPriorite') && $t->getPriorite() === 'MOYENNE')),
            'low_priority_tasks' => count(array_filter($tasks, fn($t) => method_exists($t, 'getPriorite') && $t->getPriorite() === 'BASSE')),
        ];
    }

    /**
     * Récupère les activités récentes pour une liste de projets
     * Get recent activities for a list of projects
     * 
     * @param array $projects Liste des projets / List of projects
     * @return array Activités formatées / Formatted activities
     */
    private function getRecentActivitiesForProjects(array $projects): array
    {
        try {
            $projectIds = array_map(fn($project) => $project->getId(), $projects);
            $activities = $this->activityRepository->findRecentForProjects($projectIds, 10);
            
            return array_map(function($activity) {
                $user = $activity->getUtilisateur();
                return [
                    'id' => $activity->getId(),
                    'type' => $activity->getType(),
                    'description' => $activity->getDescription(),
                    'dateCreation' => $activity->getDateCreation() ? $activity->getDateCreation()->format('Y-m-d H:i:s') : null,
                    'user' => $user ? [
                        'id' => $user->getId(),
                        'prenom' => $user->getPrenom(),
                        'nom' => $user->getNom(),
                        'avatar' => $user->getAvatar()
                    ] : null,
                    'entityType' => $activity->getEntityType(),
                    'entityId' => $activity->getEntityId()
                ];
            }, $activities);
        } catch (\Exception $e) {
            // Log l'erreur et retourne un tableau vide en cas d'échec
            error_log('Erreur lors de la récupération des activités: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les activités récentes pour l'administrateur
     * Get recent activities for admin
     * 
     * @return array Activités récentes / Recent activities
     */
    public function getRecentActivitiesForAdmin(): array
    {
        try {
            $activities = $this->activityRepository->findRecent(15);
            
            return array_map(function($activity) {
                $user = $activity->getUtilisateur();
                return [
                    'id' => $activity->getId(),
                    'type' => $activity->getType(),
                    'description' => $activity->getDescription(),
                    'dateCreation' => $activity->getDateCreation() ? $activity->getDateCreation()->format('Y-m-d H:i:s') : null,
                    'user' => $user ? [
                        'id' => $user->getId(),
                        'prenom' => $user->getPrenom(),
                        'nom' => $user->getNom(),
                        'avatar' => $user->getAvatar()
                    ] : null,
                    'entityType' => $activity->getEntityType(),
                    'entityId' => $activity->getEntityId()
                ];
            }, $activities);
        } catch (\Exception $e) {
            // Log l'erreur et retourne un tableau vide en cas d'échec
            error_log('Erreur lors de la récupération des activités admin: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les activités récentes pour l'administrateur
     * Get recent activities for admin
     * 
     * @return array Activités récentes / Recent activities
     */
    public function getRecentActivitiesForAdmin(): array
    {
        return $this->getRecentActivitiesForProjects($this->projectRepository->findAll());
    }

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

    /**
     * Vérifie si un utilisateur peut déplacer une tâche vers une autre liste
     * 
     * @param User $user L'utilisateur qui effectue le déplacement
     * @param Task $task La tâche à déplacer
     * @param TaskList $targetList La liste de destination
     * @return bool True si l'utilisateur peut effectuer le déplacement
     */
    /**
     * Vérifie si un utilisateur peut déplacer une tâche vers une autre liste
     * 
     * @param User $user L'utilisateur qui effectue le déplacement
     * @param Task $task La tâche à déplacer
     * @param TaskList $targetList La liste de destination
     * @return bool Vrai si l'utilisateur peut déplacer la tâche
     */
    /**
     * Vérifie si un utilisateur peut déplacer une tâche vers une autre liste
     * 
     * @param User $user L'utilisateur qui effectue le déplacement
     * @param Task $task La tâche à déplacer
     * @param TaskList $targetList La liste de destination
     * @return bool Vrai si l'utilisateur peut effectuer le déplacement
     */
    public function canMoveTask(User $user, Task $task, TaskList $targetList): bool
    {
        $roles = $user->getRoles();
        $currentProject = $task->getTaskList() ? $task->getTaskList()->getProject() : null;
        $targetProject = $targetList->getProject();

        if (!$currentProject || !$targetProject) {
            return false;
        }

        // Admin et Directeur peuvent tout déplacer
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
            return true;
        }

        // Chef de projet peut déplacer dans ses projets
        if (in_array('ROLE_CHEF_PROJET', $roles)) {
            return ($currentProject->getChefproject() === $user) ||
                ($targetProject->getChefproject() === $user);
        }

        // Employé peut déplacer ses propres tâches dans le même projet
        if (in_array('ROLE_EMPLOYE', $roles)) {
            $isAssigned = $this->isTaskAssignedToUser($task, $user);
            $sameProject = $currentProject->getId() === $targetProject->getId();
            return $isAssigned && $sameProject;
        }

        return false;
    }

    /**
     * Calcule les statistiques spécifiques pour un employé
     * 
     * @param User $employe L'employé pour lequel calculer les statistiques
     * @param array $assignedTasks Les tâches assignées à l'employé
     * @return array Les statistiques calculées
     */
    private function calculateEmployeStatistics(User $employe, array $assignedTasks): array
    {
        $completedTasks = array_filter($assignedTasks, fn($t) => $t->getStatut() === 'TERMINER');
        $overdueTasks = array_filter($assignedTasks, function ($t) {
            return $t->getDeadline() &&
                $t->getDeadline() < new \DateTime() &&
                $t->getStatut() !== 'TERMINER';
        });

        $totalTasks = count($assignedTasks);
        $completedCount = count($completedTasks);

        return [
            'totalAssignedTasks' => $totalTasks,
            'totalCompletedTasks' => $completedCount,
            'completedTasks' => $completedCount,
            'inProgressTasks' => count(array_filter($assignedTasks, fn($t) => $t->getStatut() === 'EN_COURS')),
            'overdueTasks' => count($overdueTasks),
            'completionRate' => $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100, 1) : 0,
            'efficiency' => $this->calculateUserEfficiency($employe)
        ];
    }

    /**
     * Récupère les utilisateurs des projets gérés par un chef de projet
     * 
     * @param User $chefProjet Le chef de projet
     * @return array Les utilisateurs des projets gérés
     */
    /**
     * Récupère les utilisateurs des projets gérés par un chef de projet
     * 
     * @param User $chefProjet Le chef de projet
     * @return array Liste des utilisateurs
     */
    /**
     * Récupère les utilisateurs des projets gérés par un chef de projet
     * 
     * @param User $chefProjet Le chef de projet
     * @return array Liste des utilisateurs
     */
    private function getUsersFromManagedProjects(User $chefProjet): array
    {
        $managedProjects = $this->projectRepository->findByChefDeproject($chefProjet);
        return $this->getUsersFromProjects($managedProjects);
    }

    /**
     * Récupère tous les utilisateurs des projets donnés (membres + chefs de projet)
     * 
     * @param array $projects Les projets à analyser
     * @return array Les utilisateurs uniques
     */
    private function getUsersFromProjects(array $projects): array
    {
        $users = [];
        foreach ($projects as $project) {
            // Ajouter les membres du projet
            $projectMembers = $project->getMembres()->toArray();
            $users = array_merge($users, $projectMembers);

            // Ajouter le chef de projet s'il existe
            if ($project->getChefproject()) {
                $users[] = $project->getChefproject();
            }
        }

        // Retourner les utilisateurs uniques
        return array_values(array_unique($users, SORT_REGULAR));
    }

    /**
     * Vérifie si une tâche est assignée à un utilisateur spécifique
     * 
     * @param Task $task La tâche à vérifier
     * @param User $user L'utilisateur à vérifier
     * @return bool True si la tâche est assignée à l'utilisateur
     */
    /**
     * Vérifie si une tâche est assignée à un utilisateur
     * 
     * @param Task $task La tâche à vérifier
     * @param User $user L'utilisateur à vérifier
     * @return bool Vrai si la tâche est assignée à l'utilisateur
     */
    /**
     * Vérifie si une tâche est assignée à un utilisateur
     * 
     * @param Task $task La tâche à vérifier
     * @param User $user L'utilisateur à vérifier
     * @return bool Vrai si la tâche est assignée à l'utilisateur
     */
    private function isTaskAssignedToUser(Task $task, User $user): bool
    {
        if (method_exists($task, 'getAssignedUser')) {
            return $task->getAssignedUser() && $task->getAssignedUser()->getId() === $user->getId();
        }
        
        if (method_exists($task, 'getTaskUsers')) {
            foreach ($task->getTaskUsers() as $taskUser) {
                if ($taskUser->getUser() && $taskUser->getUser()->getId() === $user->getId()) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Formate un utilisateur pour la réponse API
     * 
     * @param User $user L'utilisateur à formater
     * @return array Les données formatées de l'utilisateur
     */
    /**
     * Formate un utilisateur pour la réponse API
     * 
     * @param User $user L'utilisateur à formater
     * @return array Données formatées de l'utilisateur
     */
    /**
     * Formate un utilisateur pour la réponse API
     * 
     * @param User $user L'utilisateur à formater
     * @return array Données formatées de l'utilisateur
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
     * Formate un projet pour la réponse API
     * 
     * @param Project $project Le projet à formater
     * @return array Les données formatées du projet
     */
    /**
     * Formate un projet pour la réponse API
     * 
     * @param Project $project Le projet à formater
     * @return array Données formatées du projet
     */
    /**
     * Formate un projet pour la réponse API
     * 
     * @param Project $project Le projet à formater
     * @return array Données formatées du projet
     */
    private function formatProjectForResponse(Project $project): array
    {
        return [
            'id' => $project->getId(),
            'titre' => $project->getTitre(),
            'description' => $project->getDescription(),
            'statut' => $project->getStatut(),
            'dateDebut' => $project->getDateDebut() ? $project->getDateDebut()->format('Y-m-d H:i:s') : null,
            'dateFin' => $project->getDateFin() ? $project->getDateFin()->format('Y-m-d H:i:s') : null,
            'chefProjet' => $project->getChefproject() ? 
                $this->formatUserForResponse($project->getChefproject()) : null,
            'membresCount' => $project->getMembres()->count(),
            'createdAt' => $project->getCreatedAt() ? $project->getCreatedAt()->format('Y-m-d H:i:s') : null,
            'updatedAt' => $project->getUpdatedAt() ? $project->getUpdatedAt()->format('Y-m-d H:i:s') : null
        ];
    }

    /**
     * Formate une tâche pour la réponse API
     * 
     * @param Task $task La tâche à formater
     * @return array Les données formatées de la tâche
     */
    /**
     * Formate une tâche pour la réponse API
     * 
     * @param Task $task La tâche à formater
     * @return array Données formatées de la tâche
     */
    /**
     * Formate une tâche pour la réponse API
     * 
     * @param Task $task La tâche à formater
     * @return array Données formatées de la tâche
     */
    private function formatTaskForResponse(Task $task): array
    {
        $taskList = $task->getTaskList();
        $project = $taskList ? $taskList->getProject() : null;
        
        $formatted = [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'statut' => $task->getStatut(),
            'priority' => $task->getPriority(),
            'deadline' => $task->getDeadline() ? $task->getDeadline()->format('Y-m-d H:i:s') : null,
            'position' => $task->getPosition(),
            'createdAt' => $task->getCreatedAt() ? $task->getCreatedAt()->format('Y-m-d H:i:s') : null,
            'updatedAt' => $task->getUpdatedAt() ? $task->getUpdatedAt()->format('Y-m-d H:i:s') : null
        ];
        
        if ($project) {
            $formatted['project'] = [
                'id' => $project->getId(),
                'name' => $project->getTitre()
            ];
        }
        
        if ($taskList) {
            $formatted['taskList'] = [
                'id' => $taskList->getId(),
                'name' => $taskList->getNom()
            ];
        }
        
        if (method_exists($task, 'getTaskUsers')) {
            $formatted['assignedUsers'] = array_map(
                function($taskUser) {
                    return $this->formatUserForResponse($taskUser->getUser());
                },
                $task->getTaskUsers()->toArray()
            );
        } else {
            $formatted['assignedUsers'] = [];
        }
        
        return $formatted;
    }

    /**
     * Applique les filtres aux tâches
     * 
     * @param array $tasks Les tâches à filtrer
     * @param array $filters Les filtres à appliquer
     * @return array Les tâches filtrées
     */
    private function applyFilters(array $tasks, array $filters = []): array
    {
        return $this->filterTasks($tasks, $filters);
    }

    /**
     * Filtre les tâches selon les critères fournis
     * 
     * @param array $tasks Les tâches à filtrer
     * @param array $filters Les critères de filtrage
     * @return array Les tâches filtrées
     */
    private function filterTasks(array $tasks, array $filters): array
    {
        return array_filter($tasks, function ($task) use ($filters) {
            // Filtre par statut
            if (isset($filters['statut']) && $filters['statut'] !== 'all' && 
                $task->getStatut() !== $filters['statut']) {
                return false;
            }
            
            // Filtre par priorité
            if (isset($filters['priority']) && $filters['priority'] !== 'all' && 
                $task->getPriority() !== $filters['priority']) {
                return false;
            }
            
            // Filtre par utilisateur assigné
            if (isset($filters['assignedUser']) && $filters['assignedUser'] !== 'all') {
                if (method_exists($task, 'getAssignedUser')) {
                    $assignedUser = $task->getAssignedUser();
                    if (!$assignedUser || $assignedUser->getId() != $filters['assignedUser']) {
                        return false;
                    }
                } elseif (method_exists($task, 'getTaskUsers')) {
                    $assigned = false;
                    foreach ($task->getTaskUsers() as $taskUser) {
                        if ($taskUser->getUser() && $taskUser->getUser()->getId() == $filters['assignedUser']) {
                            $assigned = true;
                            break;
                        }
                    }
                    if (!$assigned) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
            
            // Filtre par projet
            if (isset($filters['project_id']) && $filters['project_id'] !== 'all') {
                $taskProject = $task->getTaskList() ? $task->getTaskList()->getProject() : null;
                if (!$taskProject || $taskProject->getId() != $filters['project_id']) {
                    return false;
                }
            }
            
            return true;
        });
    }

    /**
     * Récupère les projets accessibles par un utilisateur selon son rôle
     * 
     * @param User $user L'utilisateur
     * @return array Liste des projets accessibles
     */
    protected function getProjectsByRole(User $user): array
    {
        $roles = $user->getRoles();

        // Admin et directeur voient tous les projets
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
            return $this->projectRepository->findAll();
        }

        // Chef de projet voit les projets qu'il gère et ceux dont il est membre
        if (in_array('ROLE_CHEF_PROJET', $roles)) {
            $managed = $this->projectRepository->findByChefDeproject($user);
            $member = $this->projectRepository->findByMembre($user);
            return array_values(array_unique(array_merge($managed, $member), SORT_REGULAR));
        }

        // Employé ne voit que les projets dont il est membre
        return $this->projectRepository->findByMembre($user);
    }

    /**
     * Calcule les statistiques pour les projets et tâches fournis
     * 
     * @param array $projects Les projets à analyser
     * @param array $tasks Les tâches à analyser
     * @return array Les statistiques calculées
     */
    protected function calculateStatistics(array $projects, array $tasks): array
    {
        $now = new \DateTime();
        $oneWeekAgo = (clone $now)->modify('-1 week');
        
        $totalProjects = count($projects);
        $totalTasks = count($tasks);
        $completedTasks = 0;
        $inProgressTasks = 0;
        $overdueTasks = 0;
        $completedThisWeek = 0;
    
        // Compter les tâches par statut et priorité
        $statusCounts = [
            'A_FAIRE' => 0,
            'EN_COURS' => 0,
            'EN_REVUE' => 0,
            'TERMINE' => 0
        ];
        
        $priorityCounts = [
            'HAUTE' => 0,
            'MOYENNE' => 0,
            'BASSE' => 0
        ];

        // Parcourir toutes les tâches pour calculer les statistiques
        foreach ($tasks as $task) {
            // Compter par statut
            $status = $task->getStatut();
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
            
            // Compter par priorité
            $priority = $task->getPriority();
            if (isset($priorityCounts[$priority])) {
                $priorityCounts[$priority]++;
            }
            
            // Tâches terminées
            if ($status === 'TERMINE') {
                $completedTasks++;
                
                // Tâches terminées cette semaine
                $updatedAt = $task->getUpdatedAt();
                if ($updatedAt && $updatedAt >= $oneWeekAgo) {
                    $completedThisWeek++;
                }
            } elseif ($status === 'EN_COURS') {
                $inProgressTasks++;
            }
            
            // Tâches en retard
            $deadline = $task->getDeadline();
            if ($deadline && $deadline < $now && $status !== 'TERMINE') {
                $overdueTasks++;
            }
        }
        
        // Calculer le taux de complétion
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
        
        // Nombre d'utilisateurs actifs (ayant au moins une tâche assignée)
        $activeUsers = [];
        foreach ($tasks as $task) {
            if (method_exists($task, 'getTaskUsers')) {
                foreach ($task->getTaskUsers() as $taskUser) {
                    if ($user = $taskUser->getUser()) {
                        $activeUsers[$user->getId()] = $user;
                    }
                }
            }
        }
        $activeUsersCount = count($activeUsers);
        
        // Nombre moyen de tâches par utilisateur
        $avgTasksPerUser = $activeUsersCount > 0 ? round($totalTasks / $activeUsersCount, 1) : 0;
        
        // Compter les projets actifs
        $activeProjects = 0;
        foreach ($projects as $project) {
            if ($project->getStatut() === 'EN_COURS') {
                $activeProjects++;
            }
        }

        return [
            'totalProjects' => $totalProjects,
            'activeProjects' => $activeProjects,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'inProgressTasks' => $inProgressTasks,
            'overdueTasks' => $overdueTasks,
            'completionRate' => $completionRate,
            'activeUsers' => $activeUsersCount,
            'avgTasksPerUser' => $avgTasksPerUser,
            'completedThisWeek' => $completedThisWeek,
            'statusCounts' => $statusCounts,
            'priorityCounts' => $priorityCounts
        ];
    }

    /**
     * Récupère les activités récentes pour une liste de projets
     * 
     * @param array $projects Les projets pour lesquels récupérer les activités
     * @param int $limit Nombre maximum d'activités à retourner
     * @return array Les activités récentes formatées
     */
    private function getRecentActivitiesForProjects(array $projects, int $limit = 10): array
    {
        if (empty($projects)) {
            return [];
        }
    
        $projectIds = array_map(fn($project) => $project->getId(), $projects);
        
        try {
            if (method_exists($this->activityRepository, 'findRecentByProjectIds')) {
                $activities = $this->activityRepository->findRecentByProjectIds($projectIds, $limit);
            } else {
                // Fallback si la méthode n'existe pas
                $activities = $this->activityRepository->findBy(
                    ['project' => $projectIds],
                    ['createdAt' => 'DESC'],
                    $limit
                );
            }
            
            // Formater les activités pour la réponse
            return array_map(function($activity) {
                $user = $activity->getUser();
                $project = $activity->getProject();
                
                return [
                    'id' => $activity->getId(),
                    'type' => $activity->getType(),
                    'description' => $activity->getDescription(),
                    'createdAt' => $activity->getCreatedAt() ? $activity->getCreatedAt()->format('Y-m-d H:i:s') : null,
                    'user' => $user ? [
                        'id' => $user->getId(),
                        'fullName' => trim($user->getPrenom() . ' ' . $user->getNom()),
                        'avatar' => $user->getAvatar()
                    ] : null,
                    'project' => $project ? [
                        'id' => $project->getId(),
                        'name' => $project->getTitre()
                    ] : null,
                    'entityType' => $activity->getEntityType(),
                    'entityId' => $activity->getEntityId()
                ];
            }, $activities);
        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération des activités: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les activités récentes pour un utilisateur spécifique
     * 
     * @param User $user L'utilisateur pour lequel récupérer les activités
     * @param int $limit Nombre maximum d'activités à retourner
     * @return array Les activités récentes formatées
     */
    private function getRecentActivitiesForUser(User $user, int $limit = 10): array
    {
        try {
            if (method_exists($this->activityRepository, 'findRecentByUser')) {
                $activities = $this->activityRepository->findRecentByUser($user, $limit);
            } else {
                // Fallback: récupérer les projets de l'utilisateur et utiliser la méthode existante
                $projects = $this->projectRepository->findByMembre($user);
                return $this->getRecentActivitiesForProjects($projects, $limit);
            }
            
            // Formater les activités pour la réponse
            return array_map(function($activity) use ($user) {
                $project = $activity->getProject();
                $actor = $activity->getUser();
            
                return [
                    'id' => $activity->getId(),
                    'type' => $activity->getType(),
                    'description' => $activity->getDescription(),
                    'createdAt' => $activity->getCreatedAt() ? $activity->getCreatedAt()->format('Y-m-d H:i:s') : null,
                    'project' => $project ? [
                        'id' => $project->getId(),
                        'name' => $project->getTitre()
                    ] : null,
                    'actor' => $actor ? [
                        'id' => $actor->getId(),
                        'fullName' => trim($actor->getPrenom() . ' ' . $actor->getNom()),
                        'avatar' => $actor->getAvatar()
                    ] : null,
                    'entityType' => $activity->getEntityType(),
                    'entityId' => $activity->getEntityId()
                ];
            }, $activities);
        } catch (\Exception $e) {
        error_log('Erreur lors de la récupération des activités utilisateur: ' . $e->getMessage());
        return [];
    }
}

    /**
     * Calcule l'efficacité d'un utilisateur basée sur les tâches terminées à temps
     * 
     * @param User $user L'utilisateur à évaluer
     * @return float Le taux d'efficacité en pourcentage (0-100)
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
        if ($task->getStatut() === 'TERMINE') {
            $totalCompleted++;
            
            // Vérifier si la tâche a été terminée à temps
            $deadline = $task->getDeadline();
            $completedAt = $task->getUpdatedAt();
            
            if ($deadline && $completedAt && $completedAt <= $deadline) {
                $onTimeCompletions++;
            }
        }
    }

    return $totalCompleted > 0 ? round(($onTimeCompletions / $totalCompleted) * 100, 1) : 0.0;
}

    /**
     * Calcule le taux de remplissage des listes de tâches pour des projets donnés
     * 
     * @param array $projects Les projets à analyser
     * @return float Le taux de remplissage en pourcentage (0-100)
     */
    /**
     * Calcule le taux de remplissage des listes de tâches pour des projets donnés
     * 
     * @param array $projects Les projets à analyser
     * @return float Le taux de remplissage en pourcentage (0-100)
     */
    private function calculateProjectFillRate(array $projects): float
{
    if (empty($projects)) {
        return 0.0;
    }
    
    $totalTaskLists = 0;
    $completedTaskLists = 0;
    
    foreach ($projects as $project) {
        $projectTaskLists = $project->getTaskLists();
        $totalTaskLists += $projectTaskLists->count();
        
        foreach ($projectTaskLists as $taskList) {
            $tasks = $taskList->getTasks();
            $totalTasks = $tasks->count();
            
            if ($totalTasks === 0) {
                continue; // Liste vide, on ne la compte pas
            }
            
            $completedTasks = $tasks->filter(fn($task) => $task->getStatut() === 'TERMINE')->count();
            
            // Si toutes les tâches sont terminées, on compte la liste comme complétée
            if ($completedTasks >= $totalTasks) {
                $completedTaskLists++;
            }
        }
    }
    
    return $totalTaskLists > 0 ? round(($completedTaskLists / $totalTaskLists) * 100, 1) : 0.0;
}

    /**
     * Déplace une tâche vers une nouvelle liste et position
     * 
     * @param Task $task La tâche à déplacer
     * @param TaskList $newList La nouvelle liste de la tâche
     * @param int $newPosition La nouvelle position de la tâche dans la liste
     * @param User $user L'utilisateur qui effectue le déplacement
     * @param Project $oldProject Le projet d'origine de la tâche
     * @param Project $newProject Le nouveau projet de la tâche
     * @return array Réponse avec le succès du déplacement et les informations de la tâche
     */
    /**
     * Déplace une tâche vers une nouvelle liste et position
     * 
     * @param Task $task La tâche à déplacer
     * @param TaskList $newList La nouvelle liste de la tâche
     * @param int $newPosition La nouvelle position de la tâche dans la liste
     * @param User $user L'utilisateur qui effectue le déplacement
     * @param Project $oldProject Le projet d'origine de la tâche
     * @param Project $newProject Le nouveau projet de la tâche
     * @return array Réponse avec le succès du déplacement et les informations de la tâche
     */
    public function moveTask(Task $task, TaskList $newList, int $newPosition, User $user, Project $oldProject, Project $newProject): array
{
    try {
        $this->kanbanService->moveTask($task, $newList, $newPosition);

        // Log spécifique selon le changement de projet
        if ($oldProject->getId() !== $newProject->getId()) {
            $this->activityLogger->logTaskTransfer(
                $user,
                $oldProject,
                $newProject
            );
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