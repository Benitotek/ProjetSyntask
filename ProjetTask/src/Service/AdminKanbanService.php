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

    public function assignUserATProject(User $user, Project $project, User $assignedBy): array
    {
        // Validate the user and project
        if (!$user || !$project) {
            return ['success' => false, 'message' => 'Utilisateur ou projet introuvable.'];
        }

        // Check the user's permission to assign
        if (!$this->canAssignToProject($assignedBy, $project)) {
            return ['success' => false, 'message' => 'Droits insuffisants pour cette assignation.'];
        }

        // Assign the user to the project
        $project->addMembre($user);
        $this->entityManager->flush();

        // Log the activity
        $this->activityLogger->logProjectAssignment($user, $project, $assignedBy);

        // Notify the user
        $this->notificationService->createProjectAssignmentNotification($project, $user, $assignedBy);

        return ['success' => true, 'message' => 'Utilisateur assigné au projet.'];
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
        $memberProjects = $this->projectRepository->findByMembre($chefProjet);
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
     * 👨‍💼 Données Kanban pour Employé  
     */
    private function getEmployeKanbanData(User $employe, array $filters = []): array
    {
        // Projets où l'employé est membre  
        $projects = $this->projectRepository->findByMembre($employe);
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

            // Vérifier si déjà assigné  
            if ($task->getAssignedUser() === $user) {
                return ['success' => false, 'message' => 'Utilisateur déjà assigné à cette tâche'];
            }

            // Assigner  
            $task->setAssignedUser($user);
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

        //     // Seulement les membres de ses projets
        //     return $this->getUsersFromManagedProjects($currentUser);
        // }

        // // Employé ne peut assigner personne
        // return [];
    }
}
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