<?php

// namespace App\Service;

// use App\Entity\Project;
// use App\Entity\Task;
// use App\Entity\TaskList;
// use App\Entity\User;
// use App\Enum\TaskStatut;
// use App\Repository\ActivityRepository;
// use App\Repository\ProjectRepository;
// use App\Repository\TaskRepository;
// use App\Repository\UserRepository;
// use App\Repository\TaskListRepository;
// use Doctrine\ORM\EntityManagerInterface;
// use Knp\Component\Pager\PaginatorInterface;
// use Symfony\Component\Security\Core\Security;
// use Symfony\Component\Security\Core\Exception\AccessDeniedException;


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

            if (isset($filters['assigned_user']) && $filters['assigned_user'] !== 'all') {
                $assignedUsers = $task->getTaskUsers();
                $assignedUserIds = array_map(fn($tu) => $tu->getUser()->getId(), $assignedUsers);

                if (!in_array($filters['assigned_user'], $assignedUserIds)) {
                    return false;
                }
            }

            return true;
        }));
    }

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

        // L'admin peut tout faire
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return true;
        }

        // Le directeur peut déplacer dans ses projets
        if (in_array('ROLE_DIRECTEUR', $roles, true)) {
            return $this->projectRepository->isUserProjectDirector($user, $targetProject);
        }

        // Le chef de projet peut déplacer dans ses projets
        if (in_array('ROLE_CHEF_PROJET', $roles, true)) {
            return $this->projectRepository->isUserProjectManager($user, $targetProject);
        }

        // L'employé ne peut déplacer que ses propres tâches dans les listes de ses projets
        if (in_array('ROLE_EMPLOYE', $roles, true)) {
            $isAssigned = $this->isTaskAssignedToUser($task, $user);
            $isProjectMember = $targetProject->getMembres()->contains($user);
            return $isAssigned && $isProjectMember;
        }

        return false;
    }

    /**
     * Calcule les statistiques spécifiques pour un employé
     * 
     * @param User $employe L'employé pour lequel calculer les statistiques
     * @param array $assignedTasks Tâches assignées à l'employé
     * @return array Statistiques de l'employé
     */
    private function calculateEmployeStatistics(User $employe, array $assignedTasks): array
    {
        $now = new \DateTime();
        $oneWeekAgo = (clone $now)->modify('-1 week');

        $totalTasks = count($assignedTasks);
        $completedTasks = 0;
        $inProgressTasks = 0;
        $overdueTasks = 0;
        $completedThisWeek = 0;
        $efficiency = $this->calculateUserEfficiency($employe);

        foreach ($assignedTasks as $task) {
            if ($task->getStatut() === TaskStatut::TERMINE) {
                $completedTasks++;

                if ($task->getDateFin() && $task->getDateFin() >= $oneWeekAgo) {
                    $completedThisWeek++;
                }
            } elseif ($task->getStatut() === TaskStatut::EN_COURS) {
                $inProgressTasks++;
            }

            if ($task->getDateEcheance() && $task->getDateEcheance() < $now && $task->getStatut() !== TaskStatut::TERMINE) {
                $overdueTasks++;
            }
        }

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'in_progress_tasks' => $inProgressTasks,
            'overdue_tasks' => $overdueTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0,
            'completed_this_week' => $completedThisWeek,
            'efficiency' => $efficiency
        ];
    }

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
     * @return array Liste des utilisateurs uniques
     */
    private function getUsersFromProjectsList(array $projects): array
    {
        $users = [];
        $userIds = [];

        foreach ($projects as $project) {
            // Ajouter le chef de projet
            $projectManager = $project->getChefProject();
            if ($projectManager && !in_array($projectManager->getId(), $userIds, true)) {
                $userIds[] = $projectManager->getId();
                $users[] = $projectManager;
            }

            // Ajouter les membres du projet
            foreach ($project->getMembres() as $member) {
                if (!in_array($member->getId(), $userIds, true)) {
                    $userIds[] = $member->getId();
                    $users[] = $member;
                }
            }
        }

        return $users;
    }

    /**
     * Vérifie si une tâche est assignée à un utilisateur spécifique
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
            'chefProjet' => $project->getChefProject() ? $this->formatUserForResponse($project->getChefProject()) : null,
            'membres' => array_map([$this, 'formatUserForResponse'], $project->getMembres()->toArray())
        ];
    }

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
            'priorite' => $task->getPriorite(),
            'dateCreation' => $task->getDateCreation() ? $task->getDateCreation()->format('Y-m-d H:i:s') : null,
            'dateEcheance' => $task->getDateEcheance() ? $task->getDateEcheance()->format('Y-m-d H:i:s') : null,
            'dateFin' => $task->getDateFin() ? $task->getDateFin()->format('Y-m-d H:i:s') : null,
            'task_list_id' => $taskList ? $taskList->getId() : null,
            'project_id' => $project ? $project->getId() : null,
            'project_name' => $project ? $project->getTitre() : null,
            'assigned_users' => []
        ];

        // Ajouter les utilisateurs assignés
        if (method_exists($task, 'getTaskUsers')) {
            foreach ($task->getTaskUsers() as $taskUser) {
                if ($taskUser->getUser()) {
                    $formatted['assigned_users'][] = $this->formatUserForResponse($taskUser->getUser());
                }
            }
        } elseif (method_exists($task, 'getAssignedUser') && $task->getAssignedUser()) {
            $formatted['assigned_users'][] = $this->formatUserForResponse($task->getAssignedUser());
        }

        return $formatted;
    }

    /**
     * Filtre les tâches selon les critères fournis
     * 
     * @param array $tasks Les tâches à filtrer
     * @param array $filters Les critères de filtrage
     * @return array Les tâches filtrées
     */
    private function filterTasks(array $tasks, array $filters = []): array
    {
        return array_filter($tasks, function ($task) use ($filters) {
            // Filtre par statut
            if (
                isset($filters['statut']) && $filters['statut'] !== 'all' &&
                $task->getStatut() !== $filters['statut']
            ) {
                return false;
            }

            // Filtre par priorité
            if (
                isset($filters['priority']) && $filters['priority'] !== 'all' &&
                $task->getPriority() !== $filters['priority']
            ) {
                return false;
            }

            // Filtre par utilisateur assigné
            if (isset($filters['assigned_user']) && $filters['assigned_user'] !== 'all') {
                $assignedUsers = $task->getTaskUsers();
                $assignedUserIds = array_map(fn($tu) => $tu->getUser()->getId(), $assignedUsers);

                if (!in_array($filters['assigned_user'], $assignedUserIds)) {
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
    private function getProjectsByRole(User $user): array
    {
        $roles = $user->getRoles();

        // Admin et directeur voient tous les projets
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_DIRECTEUR', $roles, true)) {
            return $this->projectRepository->findAll();
        }

        // Chef de projet voit les projets qu'il gère et ceux dont il est membre
        if (in_array('ROLE_CHEF_PROJET', $roles, true)) {
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
    private function calculateStatistics(array $projects, array $tasks): array
    {
        $now = new \DateTime();
        $oneWeekAgo = (clone $now)->modify('-1 week');

        $totalProjects = count($projects);
        $activeProjects = 0;
        $totalTasks = count($tasks);
        $completedTasks = 0;
        $inProgressTasks = 0;
        $overdueTasks = 0;
        $completedThisWeek = 0;

        // Compter les projets actifs
        foreach ($projects as $project) {
            if ($project->getStatut() === 'en_cours') {
                $activeProjects++;
            }
        }

        // Analyser les tâches
        foreach ($tasks as $task) {
            if ($task->getStatut() === TaskStatut::TERMINE) {
                $completedTasks++;

                // Vérifier si la tâche a été terminée cette semaine
                if ($task->getDateFin() && $task->getDateFin() >= $oneWeekAgo) {
                    $completedThisWeek++;
                }
            } elseif ($task->getStatut() === TaskStatut::EN_COURS) {
                $inProgressTasks++;
            }

            // Vérifier les tâches en retard
            if (
                $task->getDateEcheance() && $task->getDateEcheance() < $now &&
                $task->getStatut() !== TaskStatut::TERMINE
            ) {
                $overdueTasks++;
            }
        }

        // Calculer les taux
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        // Compter les utilisateurs actifs (ayant au moins une tâche)
        $activeUsers = [];
        foreach ($tasks as $task) {
            if (method_exists($task, 'getAssignedUser') && $task->getAssignedUser()) {
                $activeUsers[$task->getAssignedUser()->getId()] = true;
            }

            if (method_exists($task, 'getTaskUsers')) {
                foreach ($task->getTaskUsers() as $taskUser) {
                    if ($taskUser->getUser()) {
                        $activeUsers[$taskUser->getUser()->getId()] = true;
                    }
                }
            }
        }

        $activeUsersCount = count($activeUsers);
        $avgTasksPerUser = $activeUsersCount > 0 ? round($totalTasks / $activeUsersCount, 1) : 0;

        // Compter les tâches par statut
        $statusCounts = [
            'not_started' => 0,
            'in_progress' => 0,
            'in_review' => 0,
            'completed' => 0
        ];

        foreach ($tasks as $task) {
            switch ($task->getStatut()) {
                case TaskStatut::A_FAIRE:
                    $statusCounts['not_started']++;
                    break;
                case TaskStatut::EN_COURS:
                    $statusCounts['in_progress']++;
                    break;
                case TaskStatut::EN_REVUE:
                    $statusCounts['in_review']++;
                    break;
                case TaskStatut::TERMINE:
                    $statusCounts['completed']++;
                    break;
            }
        }

        // Compter les tâches par priorité
        $priorityCounts = [
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];

        foreach ($tasks as $task) {
            if (method_exists($task, 'getPriorite')) {
                $priority = strtolower($task->getPriorite());
                if (isset($priorityCounts[$priority])) {
                    $priorityCounts[$priority]++;
                }
            }
        }

        return [
            'projectsTotal' => $totalProjects,
            'activeProjects' => $activeProjects,
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
                // Fallback: récupérer les activités de chaque projet individuellement
                $activities = [];
                foreach ($projects as $project) {
                    $projectActivities = $this->activityRepository->findBy(
                        ['project' => $project],
                        ['createdAt' => 'DESC'],
                        $limit
                    );
                    $activities = array_merge($activities, $projectActivities);
                }

                // Trier par date décroissante et limiter
                usort($activities, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
                $activities = array_slice($activities, 0, $limit);
            }

            // Formater les activités pour la réponse
            return array_map(function ($activity) {
                return [
                    'id' => $activity->getId(),
                    'action' => $activity->getAction(),
                    'details' => $activity->getDetails(),
                    'createdAt' => $activity->getCreatedAt() ? $activity->getCreatedAt()->format('Y-m-d H:i:s') : null,
                    'user' => $activity->getUser() ? $this->formatUserForResponse($activity->getUser()) : null,
                    'project' => $activity->getProject() ? [
                        'id' => $activity->getProject()->getId(),
                        'name' => $activity->getProject()->getTitre()
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
            return array_map(function ($activity) {
                return [
                    'id' => $activity->getId(),
                    'action' => $activity->getAction(),
                    'details' => $activity->getDetails(),
                    'createdAt' => $activity->getCreatedAt() ? $activity->getCreatedAt()->format('Y-m-d H:i:s') : null,
                    'user' => $activity->getUser() ? $this->formatUserForResponse($activity->getUser()) : null,
                    'project' => $activity->getProject() ? [
                        'id' => $activity->getProject()->getId(),
                        'name' => $activity->getProject()->getTitre()
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
            if ($task->getStatut() === TaskStatut::TERMINE && $task->getDateFin()) {
                $totalCompleted++;
                $deadline = $task->getDateEcheance();
                $completedAt = $task->getDateFin();

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
    private function calculateProjectFillRate(array $projects): float
    {
        if (empty($projects)) {
            return 0.0;
        }

        $totalTaskLists = 0;
        $completedTaskLists = 0;

        foreach ($projects as $project) {
            $projectTaskLists = $project->getTaskLists();

            foreach ($projectTaskLists as $taskList) {
                $totalTasks = $taskList->getTasks()->count();
                $completedTasks = $taskList->getTasks()->filter(
                    fn($task) => $task->getStatut() === TaskStatut::TERMINE
                )->count();

                // Si toutes les tâches sont terminées, on compte la liste comme complétée
                if ($completedTasks >= $totalTasks) {
                    $completedTaskLists++;
                }

                $totalTaskLists++;
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
    public function moveTask(Task $task, TaskList $newList, int $newPosition, User $user, Project $oldProject, Project $newProject): array
    {
        try {
            $this->kanbanService->moveTask($task, $newList, $newPosition);

            // Log spécifique selon le changement de projet
            if ($oldProject->getId() !== $newProject->getId()) {
                $this->activityLogger->logTaskTransfer(
                    $user,
                    $oldProject,
                    $newProject,
                    $task
                );
            }

            // Mettre à jour la date de modification
            $task->setUpdatedAt(new \DateTime());
            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return [
                'success' => true,
                'task' => $this->formatTaskForResponse($task),
                'message' => 'Tâche déplacée avec succès.'
            ];
        } catch (\Exception $e) {
            error_log('Erreur lors du déplacement de la tâche: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Une erreur est survenue lors du déplacement de la tâche.',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les données Kanban pour un employé
     * 
     * @param User $employe L'employé pour lequel récupérer les données
     * @param array $filters Filtres à appliquer
     * @return array Données formatées pour le Kanban
     */
    private function getEmployeKanbanData(User $employe, array $filters = []): array
    {
        // Récupérer les tâches assignées à l'employé
        $assignedTasks = $this->taskRepository->findByAssignedUser($employe);

        // Appliquer les filtres
        $assignedTasks = $this->applyFilters($assignedTasks, $filters);

        // Récupérer les projets des tâches assignées
        $projectIds = [];
        foreach ($assignedTasks as $task) {
            $taskList = $task->getTaskList();
            if ($taskList && $taskList->getProject()) {
                $projectIds[$taskList->getProject()->getId()] = true;
            }
        }

        $projects = [];
        if (!empty($projectIds)) {
            $projects = $this->projectRepository->findBy(['id' => array_keys($projectIds)]);
        }

        // Récupérer les listes de tâches des projets
        $taskLists = [];
        foreach ($projects as $project) {
            $projectTaskLists = $this->taskListRepository->findBy(['projet' => $project]);
            $taskLists = array_merge($taskLists, $projectTaskLists);
        }

        // Calculer les statistiques spécifiques à l'employé
        $statistics = $this->calculateEmployeStatistics($employe, $assignedTasks);

        // Récupérer les activités récentes de l'employé
        $recentActivities = $this->getRecentActivitiesForUser($employe);

        return [
            'projects' => $projects,
            'taskLists' => $taskLists,
            'tasks' => $assignedTasks,
            'users' => [$this->formatUserForResponse($employe)],
            'statistics' => $statistics,
            'recentActivities' => $recentActivities,
            'userRole' => 'EMPLOYE',
        ];
    }
}
