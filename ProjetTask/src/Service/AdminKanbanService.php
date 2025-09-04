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
        $users = $this->userRepository->findByProjects($managedProjects);  
        
        return [  
            'projects' => $allProjects,  
            'tasks' => $this->getFilteredTasks($tasks, $filters),  
            'users' => $users,  
            'taskLists' => $taskLists,  
            'statistics' => $this-> getGlobalStatistics($allProjects, $tasks),  
            'recentActivities' => $this->getRecentActivities($managedProjects),  
            'userRole' => 'CHEF_PROJET',  
            'managedProjects' => $managedProjects  // Projets où il peut assigner  
        ];  
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
        $users = $this->userRepository->findByProjects($projects);  
        
        return [  
            'projects' => $projects,  
            'tasks' => $this-> getFilteredTasks($allProjectTasks, $filters),  
            'assignedTasks' => $assignedTasks, // Tâches spécifiques à l'employé  
            'users' => $users,  
            'taskLists' => $taskLists,  
            'statistics' => $this->getGlobalStatistics($employe, $assignedTasks),  
            'recentActivities' => $this->getRecentActivities($projects),  
            'overdueTasks' => $this->getOverdueTasks(
                $employe, 
                array_merge(
                    $this->taskRepository->findByAssignedUserAndStatus($employe, TaskStatut::IN_PROGRESS),
                    $this->taskRepository->findByAssignedUserAndStatus($employe, TaskStatut::TO_DO)
                
                    
            )
            $assignedTasks),  
            'userRole' => 'EMPLOYE',  
            'managedProjects' => $projects  // Projets où il peut assigner
        ];  
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
            $this->activityLogger->logProjectAssignment($project, $user, $assignedBy);  
            
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
            $this->activityLogger->logTaskAssignment($task, $user, $assignedBy);  
            
            // Notification  
            $this->notificationService->createTaskAssignmentNotification($task, $user, $assignedBy);  
            
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
            
            // Seuls Admin et Directeur peuvent promouvoir  
            $promoterRoles = $promotedBy->getRoles();  
            if (!in_array('ROLE_ADMIN', $promoterRoles) && !in_array('ROLE_DIRECTEUR', $promoterRoles)) {  
                return ['success' => false, 'message' => 'Droits insuffisants pour cette promotion'];  
            }  
            
            // Ajouter le rôle CHEF_PROJET si nécessaire  
            $userRoles = $user->getRoles();  
            if (!in_array('ROLE_CHEF_PROJET', $userRoles)) {  
                $userRoles[] = 'ROLE_CHEF_PROJET';  
                $user->setRoles($userRoles);  
            }  
            
            // Assigner comme chef de projet  
            $project->setChefproject($user);  
            
            // S'assurer qu'il est membre du projet  
            if (!$project->getMembres()->contains($user)) {  
                $project->addMembre($user);  
            }  
            
            $this->entityManager->flush();  
            
            // Log de l'activité  
            $this->activityLogger->logChefProjetPromotion($project, $user, $promotedBy);  
            
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
     * Récupère toutes les données pour le Kanban admin global
     */
    public function getAllKanbanData(array $filters = []): array
    {
        $projects = $this->getFilteredProjects($filters);
        $tasks = $this->getFilteredTasks($filters);
        $users = $this->getAllActiveUsers();
        $taskLists = $this->getGlobalTaskLists($projects);

        return [
            'projects' => $projects,
            'tasks' => $tasks,
            'users' => $users,
            'taskLists' => $taskLists,
            'statistics' => $this->getGlobalStatistics(),
            'recentActivities' => $this->getRecentActivities(),
            'overdueTasks' => $this->getOverdueTasks(),
            'performanceMetrics' => $this->getPerformanceMetrics(),
            'workloadDistribution' => $this->getWorkloadDistribution()
        ];
    }

    /**
     * Statistiques globales avancées
     */
    public function getGlobalStatistics(): array
    {
        $activeProjects = $this->projectRepository->findActiveProjects();
        $allTasks = $this->taskRepository->findAll();
        $allUsers = $this->userRepository->findActiveUsers();

        // Calculs de productivité
        $completedThisWeek = $this->taskRepository->findTasksCompletedThisWeek();
        $overdueTasks = $this->taskRepository->findOverdue();

        // Calculs budgétaires (si applicable)
        $budgetStats = $this->calculateBudgetStatistics($activeProjects);

        return [
            'totalProjects' => count($this->projectRepository->findAll()),
            'activeProjects' => count($activeProjects),
            'archivedProjects' => $this->projectRepository->countBystatut(['ARCHIVER']),
            'totalTasks' => count($allTasks),
            'completedTasks' => count(array_filter($allTasks, fn($t) => $t->getStatut() === 'TERMINER')),
            'inProgressTasks' => count(array_filter($allTasks, fn($t) => $t->getStatut() === 'EN_COURS')),
            'pendingTasks' => count(array_filter($allTasks, fn($t) => $t->getStatut() === 'EN_ATTENTE')),
            'overdueTasks' => count($overdueTasks),
            'totalUsers' => count($allUsers),
            'activeUsers' => count(array_filter($allUsers, fn($u) => $u->getStatut() === 'ACTIF')),
            'completedThisWeek' => count($completedThisWeek),
            'avgTasksPerUser' => count($allUsers) > 0 ? round(count($allTasks) / count($allUsers), 1) : 0,
            'completionRate' => count($allTasks) > 0 ? round((count(array_filter($allTasks, fn($t) => $t->getStatut() === 'TERMINER')) / count($allTasks)) * 100, 1) : 0,
            'budgetStats' => $budgetStats
        ];
    }

    /**
     * Récupère les projets filtrés
     */
    private function getFilteredProjects(array $filters): array
    {
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            return $this->projectRepository->findByStatut($filters['status']);
        }

        if (isset($filters['chef_projet']) && $filters['chef_projet']) {
            $chef = $this->userRepository->find($filters['chef_projet']);
            return $chef ? $this->projectRepository->findByChefDeproject($chef) : [];
        }

        return $this->projectRepository->findRecentWithStats();
    }

    /**
     * Récupère les tâches filtrées avec relations
     */
    private function getFilteredTasks(array $filters): array
    {
        $queryBuilder = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.taskList', 'tl')
            ->leftJoin('tl.project', 'p')
            ->leftJoin('t.taskUsers', 'tu')
            ->leftJoin('tu.user', 'u')
            ->leftJoin('t.taskTags', 'tt')
            ->leftJoin('tt.tag', 'tag')
            ->addSelect('tl', 'p', 'tu', 'u', 'tt', 'tag')
            ->orderBy('t.position', 'ASC');

        // Filtre par projet
        if (isset($filters['project_id']) && $filters['project_id']) {
            $queryBuilder->andWhere('p.id = :projectId')
                ->setParameter('projectId', $filters['project_id']);
        }

        // Filtre par utilisateur assigné
        if (isset($filters['assigned_user']) && $filters['assigned_user']) {
            $queryBuilder->andWhere('u.id = :userId')
                ->setParameter('userId', $filters['assigned_user']);
        }

        // Filtre par priorité
        if (isset($filters['priority']) && $filters['priority'] !== 'all') {
            $queryBuilder->andWhere('t.priority = :priority')
                ->setParameter('priority', $filters['priority']);
        }

        // Filtre par statut
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $queryBuilder->andWhere('t.statut = :status')
                ->setParameter('status', $filters['status']);
        }

        // Filtre par échéance proche
        if (isset($filters['due_soon']) && $filters['due_soon']) {
            $queryBuilder->andWhere('t.deadline BETWEEN :now AND :nextWeek')
                ->setParameter('now', new \DateTime())
                ->setParameter('nextWeek', new \DateTime('+7 days'));
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Récupère toutes les listes de tâches globales
     */
    private function getGlobalTaskLists(array $projects): array
    {
        $taskLists = [];
        foreach ($projects as $project) {
            $projectLists = $this->taskListRepository->findByProjectWithTasksOrdered($project);
            $taskLists = array_merge($taskLists, $projectLists);
        }

        return $taskLists;
    }

    /**
     * Déplace une tâche (utilise le service existant)
     */
    public function moveTask(int $taskId, int $newListId, int $newPosition): array
    {
        try {
            $task = $this->taskRepository->find($taskId);
            $newList = $this->taskListRepository->find($newListId);

            if (!$task || !$newList) {
                return ['success' => false, 'message' => 'Tâche ou liste introuvable'];
            }

            $oldList = $task->getTaskList();
            $oldProject = $oldList->getProject();
            $newProject = $newList->getProject();

            // Utilise le service Kanban existant
            $this->kanbanService->moveTask($task, $newList, $newPosition);

            // Log de l'activité
            $this->activityLogger->logActivity(

                $this->security->getUser(),
                $task->getId(),
                $task->getTaskList()->getProject(),
                $oldList->getLastName(),
                $newList->getLastName()
            );

            // Notification si changement de projet
            if ($oldProject !== $newProject) {
                $this->notificationService->notifyTaskMovedToAnotherProject(

                    $task,
                    $oldProject,
                    $newProject,
                    $this->security->getUser()
                );
            }

            $this->entityManager->flush();

            return [
                'success' => true,
                'message' => 'Tâche déplacée avec succès',
                'task' => $this->formatTaskForResponse($task),
                'statistics' => $this->getGlobalStatistics()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors du déplacement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crée une nouvelle tâche rapide
     */
    public function createQuickTask(array $data): array
    {
        try {
            $taskList = $this->taskListRepository->find($data['taskListId']);
            if (!$taskList) {
                return ['success' => false, 'message' => 'Liste introuvable'];
            }

            $task = new Task();
            $task->setTitle($data['title']);
            $task->setDescription($data['description'] ?? '');
            $task->setTaskList($taskList);
            $task->setStatut($data['statut'] ?? 'EN_ATTENTE');
            $task->setPriorite($data['priority'] ?? 'NORMAL');
            $task->setPosition($this->taskRepository->findNextPositionInColumn($taskList));
            $task->setDateCreation(new \DateTime());

            if (isset($data['deadline']) && $data['deadline']) {
                $task->setDateButoir(new \DateTime($data['deadline']));
            }

            $this->entityManager->persist($task);

            // Assigner l'utilisateur si spécifié
            if (isset($data['assignedUserId'])) {
                $user = $this->userRepository->find($data['assignedUserId']);
                if ($user) {
                    // Logique d'assignation selon votre structure
                    // $task->addTaskUser($taskUser);
                }
            }

            $this->entityManager->flush();

            // Log de l'activité
            $this->activityLogger->logTaskCreation(
                $this->security->getUser(),
                $task->getTitle(),
                $task->getId(),
                $task->getTaskList()->getProject(),
                $this->security->getUser()
            );

            return [
                'success' => true,
                'message' => 'Tâche créée avec succès',
                'task' => $this->formatTaskForResponse($task)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Métriques de performance
     */
    public function getPerformanceMetrics(): array
    {
        $users = $this->userRepository->findActiveUsers();
        $metrics = [];

        foreach ($users as $user) {
            $userTasks = $this->taskRepository->findByAssignedUser($user);
            $completedTasks = array_filter($userTasks, fn($t) => $t->getStatut() === 'TERMINER');
            $overdueTasks = array_filter($userTasks, function ($t) {
                return $t->getDeadline() && $t->getDeadline() < new \DateTime() && $t->getStatut() !== 'TERMINER';
            });

            $metrics[] = [
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getPrenom() . ' ' . $user->getNom(),
                    'email' => $user->getEmail(),
                    'role' => $user->getRole()
                ],
                'totalTasks' => count($userTasks),
                'completedTasks' => count($completedTasks),
                'overdueTasks' => count($overdueTasks),
                'completionRate' => count($userTasks) > 0 ? round((count($completedTasks) / count($userTasks)) * 100, 1) : 0,
                'efficiency' => $this->calculateUserEfficiency($user)
            ];
        }

        return $metrics;
    }

    /**
     * Distribution de la charge de travail
     */
    public function getWorkloadDistribution(): array
    {
        $users = $this->userRepository->findActiveUsers();
        $distribution = [];

        foreach ($users as $user) {
            $activeTasks = $this->taskRepository->findByAssignedUser($user);
            $activeTasks = array_filter($activeTasks, fn($t) => $t->getStatut() !== 'TERMINER');

            $distribution[] = [
                'user' => $user->getPrenom() . ' ' . $user->getNom(),
                'activeTasks' => count($activeTasks),
                'priority' => [
                    'urgent' => count(array_filter($activeTasks, fn($t) => $t->getPriority() === 'URGENT')),
                    'normal' => count(array_filter($activeTasks, fn($t) => $t->getPriority() === 'NORMAL')),
                    'low' => count(array_filter($activeTasks, fn($t) => $t->getPriority() === 'EN_ATTENTE'))
                ]
            ];
        }

        return $distribution;
    }

    /**
     * Activités récentes avec détails
     */
    public function getRecentActivities(int $limit = 20): array
    {
        return $this->activityRepository->findRecent($limit);
    }

    /**
     * Tâches en retard
     */
    public function getOverdueTasks(): array
    {
        return $this->taskRepository->findOverdue();
    }

    /**
     * Formate une tâche pour la réponse API
     */
    private function formatTaskForResponse(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'statut' => $task->getStatut(),
            'priority' => $task->getPriorite(),
            'deadline' => $task->getDateButoir()?->format('Y-m-d'),
            'position' => $task->getPosition(),
            'project' => [
                'id' => $task->getTaskList()->getProject()->getId(),
                'name' => $task->getTaskList()->getProject()->getTitre()
            ],
            'taskList' => [
                'id' => $task->getTaskList()->getId(),
                'name' => $task->getTaskList()->getNom()
            ]
        ];
    }

    /**
     * Calcule l'efficacité d'un utilisateur
     */
    private function calculateUserEfficiency(User $user): float
    {
        // Logique de calcul d'efficacité basée sur vos critères métier
        $tasks = $this->taskRepository->findByAssignedUser($user);
        if (empty($tasks)) return 0;

        $onTimeTasks = array_filter($tasks, function ($task) {
            return $task->getStatut() === 'TERMINER' &&
                $task->getDeadline() &&
                $task->getDateCreation() <= $task->getDeadline();
        });

        return round((count($onTimeTasks) / count($tasks)) * 100, 1);
    }

    /**
     * Calcule les statistiques budgétaires
     */
    private function calculateBudgetStatistics(array $projects): array
    {
        $totalBudget = 0;
        $usedBudget = 0;

        foreach ($projects as $project) {
            if ($project->getBudget()) {
                $totalBudget += $project->getBudget();
                // Logique pour calculer le budget utilisé selon votre modèle
                // $usedBudget += $this->calculateUsedBudget($project);
            }
        }

        return [
            'total' => $totalBudget,
            'used' => $usedBudget,
            'remaining' => $totalBudget - $usedBudget,
            'utilizationRate' => $totalBudget > 0 ? round(($usedBudget / $totalBudget) * 100, 1) : 0
        ];
    }

    /**
     * Recherche globale
     */
    public function globalSearch(string $query): array
    {
        return [
            'projects' => $this->searchProjects($query),
            'tasks' => $this->searchTasks($query),
            'users' => $this->searchUsers($query)
        ];
    }

    private function searchProjects(string $query): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Project::class, 'p')
            ->where('p.titre LIKE :query OR p.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function searchTasks(string $query): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Task::class, 't')
            ->where('t.title LIKE :query OR t.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function searchUsers(string $query): array
    {
        return $this->userRepository->searchByTerm($query);
    }

    /**
     * Récupère tous les utilisateurs actifs
     */
    private function getAllActiveUsers(): array
    {
        return $this->userRepository->findActiveUsers();
    }
      /**
     * 📋 Récupérer la liste des utilisateurs assignables
     */
    public function getAssignableUsers(User $currentUser, Project $project = null): array
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
            return $this->getUsersFromManagedProjects($currentUser);
        }
        
        // Employé ne peut assigner personne
        return [];
    }

    /**
     * 🔄 Déplacer une tâche avec vérification des droits
     */
    public function moveTaskWithRoleCheck(int $taskId, int $newListId, int $newPosition, User $user): array
    {
        try {
            $task = $this->taskRepository->find($taskId);
            $newList = $this->taskListRepository->find($newListId);
            
            if (!$task || !$newList) {
                return ['success' => false, 'message' => 'Tâche ou liste introuvable'];
            }

            $oldProject = $task->getTaskList()->getProject();
            $newProject = $newList->getProject();

            // Vérifier les droits selon le rôle
            if (!$this->canMoveTask($user, $task, $newList)) {
                return ['success' => false, 'message' => 'Droits insuffisants pour ce déplacement'];
            }

            // Utiliser le service Kanban existant
            $this->kanbanService->moveTaskToColumn($task, $newList, $newPosition);

            // Log spécifique selon le changement de projet
            if ($oldProject->getId() !== $newProject->getId()) {
                $this->activityLogger->logTaskTransfer($task, $oldProject, $newProject, $user);
                
                // Notification aux chefs de projets concernés
                $this->notificationService->createTaskTransferNotification(
                    $task, $oldProject, $newProject, $user
                );
            } else {
                $this->activityLogger->logTaskMove($task, $user, 
                    $task->getTaskList()->getLastName(), 
                    $newList->getLastName()
                );
            }

            $this->entityManager->flush();

            return [
                'success' => true,
                'message' => 'Tâche déplacée avec succès',
                'task' => $this->formatTaskForResponse($task),
                'crossProject' => $oldProject->getId() !== $newProject->getId()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false, 
                'message' => 'Erreur lors du déplacement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 🔐 Vérifier si un utilisateur peut déplacer une tâche
     */
    private function canMoveTask(User $user, Task $task, TaskList $targetList): bool
    {
        $roles = $user->getRoles();
        $currentProject = $task->getTaskList()->getProject();
        $targetProject = $targetList->getProject();
        
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
            // Vérifier si c'est sa tâche et même projet
            $isAssigned = $this->isTaskAssignedToUser($task, $user);
            $sameProject = $currentProject->getId() === $targetProject->getId();
            
            return $isAssigned && $sameProject;
        }
        
        return false;
    }

    /**
     * 📊 Statistiques spécifiques pour employé
     */
    private function calculateEmployeStatistics(User $employe, array $assignedTasks): array
    {
        $completedTasks = array_filter($assignedTasks, fn($t) => $t->getStatut() === 'TERMINER');
        $overdueTasks = array_filter($assignedTasks, function($t) {
            return $t->getDeadline() && 
                   $t->getDeadline() < new \DateTime() && 
                   $t->getStatut() !== 'TERMINER';
        });
        
        return [
            'totalAssignedTasks' => count($assignedTasks),
            'completedTasks' => count($completedTasks),
            'inProgressTasks' => count(array_filter($assignedTasks, fn($t) => $t->getStatut() === 'EN_COURS')),
            'overdueTasks' => count($overdueTasks),
            'completionRate' => count($assignedTasks) > 0 ? 
                round((count($completedTasks) / count($assignedTasks)) * 100, 1) : 0,
            'efficiency' => $this->calculateUserEfficiency($employe)
        ];
    }

    /**
     * 👥 Récupérer les utilisateurs des projets gérés
     */
    private function getUsersFromManagedProjects(User $chefProjet): array
    {
        $managedProjects = $this->projectRepository->findByChefDeproject($chefProjet);
        return $this->getUsersFromProjects($managedProjects);
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
     * 🔍 Vérifier si une tâche est assignée à un utilisateur
     */
    private function isTaskAssignedToUser(Task $task, User $user): bool
    {
        // Selon votre modèle de données
        foreach ($task->getTaskUsers() as $taskUser) {
            if ($taskUser->getUser()->getId() === $user->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 📤 Formater les réponses
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

    private function formatTaskForResponse(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'statut' => $task->getStatut(),
            'priority' => $task->getPriority(),
            'deadline' => $task->getDeadline()?->format('Y-m-d H:i:s'),
            'position' => $task->getPosition(),
            'project' => [
                'id' => $task->getTaskList()->getProject()->getId(),
                'name' => $task->getTaskList()->getProject()->getTitre()
            ],
            'taskList' => [
                'id' => $task->getTaskList()->getId(),
                'name' => $task->getTaskList()->getLastName()
            ],
            'assignedUsers' => array_map(
                fn($tu) => $this->formatUserForResponse($tu->getUser()), 
                $task->getTaskUsers()->toArray()
            )
        ];
    }

    // Méthodes utilitaires supplémentaires...
    
    public function getProjectsByRole(User $user): array
    {
        $roles = $user->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
            return $this->projectRepository->findAll();
        }
        
        if (in_array('ROLE_CHEF_PROJET', $roles)) {
            $managed = $this->projectRepository->findByChefDeproject($user);
            $member = $this->projectRepository->findByMembre($user);
            return array_unique(array_merge($managed, $member), SORT_REGULAR);
        }
        
        return $this->projectRepository->findByMembre($user);
    }
}


// {

//     public function __construct(
//         private ProjectRepository $projectRepository,
//         private TaskRepository $taskRepository,
//         private UserRepository $userRepository,
//         private TaskListRepository $taskListRepository,
//         private EntityManagerInterface $entityManager
//     ) {}

//     /**
//      * Récupère toutes les données pour le Kanban admin
//      */
//     public function getAllKanbanData(): array
//     {
//         return [
//             'projects' => $this->projectRepository->findAllWithStats(),
//             'tasks' => $this->taskRepository->findAllWithRelations(),
//             'users' => $this->userRepository->findActiveUsersWithStats(),
//             'taskLists' => $this->taskListRepository->findAllOrderedByPosition(),
//             'statistics' => $this->getGlobalStatistics()
//         ];
//     }

//     /**
//      * Statistiques globales
//      */
//     public function getGlobalStatistics(): array
//     {
//         return [
//             'totalProjects' => $this->projectRepository->count([]),
//             'activeProjects' => $this->projectRepository->countByStatus('EN_COURS'),
//             'totalTasks' => $this->taskRepository->count([]),
//             'completedTasks' => $this->taskRepository->countByStatus('TERMINER'),
//             'overdueTasks' => $this->taskRepository->countOverdueTasks(),
//             'totalUsers' => $this->userRepository->countActiveUsers(),
//         ];
//     }

//     /**
//      * Déplace une tâche vers une nouvelle liste/position
//      */
//     public function moveTask(int $taskId, int $newListId, int $newPosition): bool
//     {
//         $task = $this->taskRepository->find($taskId);
//         $newList = $this->taskListRepository->find($newListId);

//         if (!$task || !$newList) {
//             return false;
//         }

//         // Mettre à jour la position des autres tâches
//         $this->updateTaskPositions($task->getTaskList(), $task->getPosition(), 'up');
//         $this->updateTaskPositions($newList, $newPosition, 'down');

//         // Déplacer la tâche
//         $task->setTaskList($newList);
//         $task->setPosition($newPosition);

//         // Mettre à jour le statut selon la colonne
//         $this->updateTaskStatusFromList($task, $newList);

//         $this->entityManager->flush();

//         return true;
//     }

//     /**
//      * Met à jour le statut de la tâche selon la liste
//      */
//     private function updateTaskStatusFromList(Task $task, TaskList $taskList): void
//     {
//         $statusMapping = [
//             'À faire' => 'EN_ATTENTE',
//             'En cours' => 'EN_COURS',
//             'En test' => 'EN_COURS',
//             'Terminé' => 'TERMINER',
//             'Validé' => 'TERMINER'
//         ];

//         $listName = $taskList->getNom();
//         if (isset($statusMapping[$listName])) {
//             $task->setStatut(TaskStatut::from($statusMapping[$listName]));
//         }
//     }

//     /**
//      * Réorganise les positions des tâches
//      */
//     private function updateTaskPositions(TaskList $taskList, int $position, string $direction): void
//     {
//         if ($direction === 'up') {
//             // Décaler vers le haut les tâches avec position >= $position
//             $this->entityManager->createQuery(
//                 'UPDATE App\Entity\Task t 
//                  SET t.position = t.position - 1 
//                  WHERE t.taskList = :taskList AND t.position >= :position'
//             )
//                 ->setParameters(['taskList' => $taskList, 'position' => $position])
//                 ->execute();
//         } else {
//             // Décaler vers le bas les tâches avec position >= $position
//             $this->entityManager->createQuery(
//                 'UPDATE App\Entity\Task t 
//                  SET t.position = t.position + 1 
//                  WHERE t.taskList = :taskList AND t.position >= :position'
//             )
//                 ->setParameters(['taskList' => $taskList, 'position' => $position])
//                 ->execute();
//         }
//     }
// }
