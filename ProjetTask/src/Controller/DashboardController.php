<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\Attribute\Security;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\Security\Core\Role\Role;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(

        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        UserRepository $userRepository
    ): Response {
        // Assurez-vous que l'utilisateur est connecté
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les projets
        $projects = $projectRepository->findAll(); // Ou une requête plus spécifique

        // Récupérer les tâches
        $tasks = $taskRepository->findAll(); // Ou une requête plus spécifique

        // Récupérer les utilisateurs
        $users = $userRepository->findAll();

        // Récupérer les activités récentes (désactivé car ActivityRepository n'existe pas)
        $activities = [];

        // Calculs pour les statistiques
        $completedTasks = count(array_filter($tasks, function ($task) {
            return $task->getstatut() === 'completed'; // Adaptez selon votre structure
        }));

        $pendingTasks = count(array_filter($tasks, function ($task) {
            return $task->getstatut() === 'pending'; // Adaptez selon votre structure
        }));

        $inProgressTasks = count(array_filter($tasks, function ($task) {
            return $task->getstatut() === 'in_progress'; // Adaptez selon votre structure
        }));

        return $this->render('dashboard/index.html.twig', [
            'projects' => $projects,
            'tasks' => $tasks,
            'users' => $users,
            'activities' => $activities,
            'stats' => [
                'totalProjects' => count($projects),
                'totalTasks' => count($tasks),
                'completedTasks' => $completedTasks,
                'pendingTasks' => $pendingTasks,
                'inProgressTasks' => $inProgressTasks,
                'totalUsers' => count($users),
                'completionRate' => count($tasks) > 0 ? ($completedTasks / count($tasks)) * 100 : 0,
            ],
        ]);
    }


    // Route pour le tableau de bord principal
    #[Route('/dashboard', name: 'app_dashboard')]
    public function Dashindex(
        Request $request,
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        UserRepository $userRepository
    ): Response {

        /** @var User $user */
        // Récupération de l'utilisateur actuel
        $user = $this->getUser();

        // Vérifier si l'utilisateur est admin
        $isAdmin = false;

        // Si vous utilisez les rôles Symfony standards
        if ($this->isGranted('ROLE_ADMIN')) {
            $isAdmin = true;
        }
        // OU si vous utilisez un enum Role
        // if ($user->getRole() === Role::ADMIN) {
        //     $isAdmin = true;
        // }

        if ($user->getRole() === 'ROLE_ADMIN') {
            $isAdmin = true;
        }

        // Récupérer les projets en fonction du rôle
        if ($isAdmin) {
            // Pour un admin, récupérer TOUS les projets
            $projects = $projectRepository->findAll();
        } else {
            // Pour un utilisateur normal, seulement ses projets
            $projects = $projectRepository->findBy(['user' => $user]);
        }
        // Récupérer les projets
        // $projects = $projectRepository->findBy(['user' => $user]);

        // Récupérer les tâches
        // $tasks = $taskRepository->findBy(['user' => $user]);

        // Récupération des projets
        // $projects = $projectRepository->findAll(); // Récupère tous les projets
        // OU pour récupérer uniquement les projets de l'utilisateur connecté :
        // $projects = $projectRepository->findByUser($user);
        // Si vous voulez uniquement les projets actifs
        // $projects = $projectRepository->findBy(['statut' => 'active']);
        // Si vous voulez trier les projets
        // $projects = $projectRepository->findBy([], ['dateCreation' => 'DESC']);

        // Si vous avez une méthode personnalisée dans votre repository
        // $projects = $projectRepository->findProjectsWithStats();

        // Code existant pour déterminer le statut actuel
        $currentStatut = 'votre_logique_pour_determiner_statut';

        // Récupération des tâches (si nécessaire pour d'autres parties du template)
        // $tasks = $taskRepository->findAll(); 
        // ou une requête plus spécifique
        //  Même logique pour les tâches
        if ($isAdmin) {
            $tasks = $taskRepository->findAll();
        } else {
            $tasks = $taskRepository->findBy(['user' => $user]);
        }

        // Préparer les statistiques adaptées au rôle
        // $stats = [
        //     'totalProjects' => count($projects),
        //     'totalTasks' => count($tasks),
        // Autres statistiques...
        // ];
        // Récupération des statistiques ou autres données nécessaires

        // Préparer les statistiques
        $stats = [
            'totalProjects' => count($projects),
            'totalTasks' => count($tasks),

            // Statistiques des projets par statut
            'projectsByStatus' => [
                'not_started' => count(array_filter($projects, fn($p) => $p->getStatut() === 'not_started')),
                'in_progress' => count(array_filter($projects, fn($p) => $p->getStatut() === 'in_progress')),
                'completed' => count(array_filter($projects, fn($p) => $p->getStatut() === 'completed')),
            ],

            // Statistiques des tâches par statut
            'tasksByStatus' => [
                'not_started' => count(array_filter($tasks, fn($t) => $t->getStatut() === 'not_started')),
                'in_progress' => count(array_filter($tasks, fn($t) => $t->getStatut() === 'in_progress')),
                'completed' => count(array_filter($tasks, fn($t) => $t->getStatut() === 'completed')),
            ],

            // Taux de complétion (pourcentage de tâches terminées)
            'completionRate' => count($tasks) > 0
                ? round((count(array_filter($tasks, fn($t) => $t->getStatut() === 'completed')) / count($tasks)) * 100)
                : 0,

            // Statistiques temporelles (tâches par mois/semaine)
            'recentActivity' => $this->calculateRecentActivity($tasks),
        ];

        // Si vous avez besoin de statistiques plus avancées, vous pouvez 
        // utiliser des requêtes DQL personnalisées dans vos repositories

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'current_statut' => 'dashboard',
            'projects' => $projects,
            'tasks' => $tasks,
            'stats' => $stats, // Ajout de la variable stats
            'isAdmin' => $isAdmin, // Indique si l'utilisateur est admin
            'curent_statut' => $currentStatut, // Ajout de la variable current_statut
            'tachesAssignees' => [],
            'userAssignees' => [],
            'projectAssignees' => [],
        ]);
    }

    /**
     * Calcule l'activité récente basée sur les dates de création des tâches
     */
    private function calculateRecentActivity(array $tasks): array
    {
        $now = new \DateTime();
        $lastWeek = (new \DateTime())->modify('-7 days');
        $lastMonth = (new \DateTime())->modify('-30 days');

        $tasksLastWeek = array_filter($tasks, function ($task) use ($lastWeek) {
            return $task->getDateCreation() >= $lastWeek;
        });

        $tasksLastMonth = array_filter($tasks, function ($task) use ($lastMonth, $lastWeek) {
            return $task->getDateCreation() >= $lastMonth && $task->getDateCreation() < $lastWeek;
        });

        return [
            'lastWeek' => count($tasksLastWeek),
            'lastMonth' => count($tasksLastMonth),
            'total' => count($tasks),
        ];

        // return $this->render('dashboard/index.html.twig', [
        //     'user' => $user,
        //     'current_statut' => $currentStatut,
        //     'projects' => $projects,
        //     'tasks' => $tasks
        // ]);
        // Vérification des rôles de l'utilisateur

        // Différentes vues selon le rôle
        // Note: Ne pas retourner de Response ici, car cette méthode doit retourner un array
        // Si vous souhaitez rediriger selon le rôle, faites-le dans l'action du contrôleur, pas ici.
    }

    /**
     * Tableau de bord pour admin et directeur
     */
    private function adminDashboard(
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        UserRepository $userRepository
    ): Response {
        // Statistiques globales
        $stats = [
            'projets' => [
                'total' => $projectRepository->countAll(),
                'en_cours' => $projectRepository->countBystatut([Project::STATUT_EN_COURS]),
                'en_attente' => $projectRepository->countBystatut([Project::STATUT_EN_ATTENTE]),
                'termines' => $projectRepository->countBystatut([Project::STATUT_TERMINE]),
            ],
            'utilisateurs' => [
                'actifs' => count($userRepository->findActiveUsers()),
                'chefs_projet' => count($userRepository->findChefsProjets()),
            ],
        ];

        // Projets récents
        $projetsRecents = $projectRepository->findRecent(5);

        // Tâches en retard
        $tachesRetard = $taskRepository->findOverdue();

        // Projets avec statistiques budgétaires
        $projetsBudget = $projectRepository->getProjectsWithBudgetStats();

        return $this->render('dashboard/admin_dashboard.html.twig', [
            'stats' => $stats,
            'projets_recents' => $projetsRecents,
            'taches_retard' => $tachesRetard,
            'projets_budget' => $projetsBudget,
        ]);
    }

    /**
     * Tableau de bord pour chef de projet
     */
    private function chefProjetDashboard(
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        User $user
    ): Response {
        // Projets où l'utilisateur est chef
        $projetsDiriges = $projectRepository->findByChefDeProjet($user);

        // Projets récents avec stats
        $projetsRecents = $projectRepository->findRecentWithStats($user, 5);

        // Tâches en retard dans les projets gérés
        $tachesRetard = [];
        foreach ($projetsDiriges as $projet) {
            $tachesProjet = $taskRepository->findOverdue();
            foreach ($tachesProjet as $tache) {
                if ($tache->getProject() && $tache->getProject()->getChef_Projet() === $user) {
                    $tachesRetard[] = $tache;
                }
            }
        }

        return $this->render('dashboard/chef_projet.html.twig', [
            'projets_diriges' => $projetsDiriges,
            'projets_recents' => $projetsRecents,
            'taches_retard' => $tachesRetard,
        ]);
    }

    /**
     * Tableau de bord pour employé
     */
    private function employeDashboard(
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        User $user
    ): Response {
        // Projets où l'utilisateur est membre
        $projetsAssignes = $projectRepository->findByAssignedUser($user);

        // Tâches assignées à l'utilisateur
        $tachesAssignees = $taskRepository->findByAssignedUser($user);

        // Tâches en retard
        $tachesRetard = array_filter($tachesAssignees, function ($tache) {
            return $tache->isOverdue();
        });

        // Tâches avec échéance proche
        $tachesProches = $taskRepository->findTasksWithDeadlineApproaching();
        $tachesProches = array_filter($tachesProches, function ($tache) use ($user) {
            return $tache->getAssignedUser() === $user;
        });

        return $this->render('dashboard/employe.html.twig', [
            'projets_assignes' => $projetsAssignes,
            'taches_assignees' => $tachesAssignees,
            'taches_retard' => $tachesRetard,
            'taches_proches' => $tachesProches,
        ]);
    }
    // End of DashboardController class restest route suivante verrif et modif a revoir

    #[Route('/directeur/dashboard', name: 'app_directeur_dashboard')]
    public function directeurDashboardStats(ProjectRepository $projectRepository, UserRepository $userRepository): Response
    {
        // Récupérer tous les projets
        $projects = $projectRepository->findAll();

        // Calculer les statistiques
        $totalProjects = count($projects);
        $activeProjects = 0;
        $pendingProjects = 0;
        $completedProjects = 0;

        foreach ($projects as $project) {
            if ($project->getStatut() === 'EN-COURS') {
                $activeProjects++;
            } elseif ($project->getStatut() === 'TERMINE') {
                $completedProjects++;
            } else {
                $pendingProjects++;
            }
        }

        // Calculer les pourcentages
        $percentActive = $totalProjects > 0 ? round(($activeProjects / $totalProjects) * 100) : 0;
        $percentCompleted = $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100) : 0;
        $percentPending = $totalProjects > 0 ? round(($pendingProjects / $totalProjects) * 100) : 0;

        // Nombre total d'employés
        $totalEmployees = $userRepository->count([]);

        // Budget total (ceci est un exemple, adaptez selon votre modèle de données)
        $totalBudget = 0;
        foreach ($projects as $project) {
            // Assurez-vous que votre entité Project a un getter pour le budget
            // Si ce n'est pas le cas, vous pouvez omettre cette partie
            if (method_exists($project, 'getBudget')) {
                $totalBudget += $project->getBudget() ?? 0;
            }
        }

        // Statistiques pour le dashboard
        $stats = [
            'total_projects' => $totalProjects,
            'active_projects' => $activeProjects,
            'total_employees' => $totalEmployees,
            'total_budget' => $totalBudget,
            'percent_active' => $percentActive,
            'percent_completed' => $percentCompleted,
            'percent_pending' => $percentPending,
        ];

        return $this->render('dashboard/directeur_dashboard.html.twig', [
            'stats' => $stats,
            'projects' => $projects,
            // Si vous avez une entité Team, vous pouvez les ajouter ici
            // 'teams' => $teamRepository->findAll(),
        ]);
    }

    #[Route('/directeur/dashboard', name: 'app_directeur_dashboard')]
    public function directeurDashboard(): Response
    {
        return $this->render('dashboard/directeur_dashboard.html.twig', [
            'controller_name' => 'DashboardController',
        ]);
    }
    #[Route('/chef-de-projet/dashboard', name: 'app_chef_de_projet_dashboard')]
    public function chefDeProjetDashboard(): Response
    {
        return $this->render('dashboard/chef_de_projet_dashboard.html.twig', [
            'controller_name' => 'DashboardController',
        ]);
    }
}
 





    // // debug rapide a modifier et revoir
    // #[Route('/chef-projet/dashboard', name: 'app_chef_projet_dashboard')]
    // public function chefProjetDashboard(): Response
    // {
    //     return $this->render('dashboard/index.html.twig', [
    //         'message' => 'Dashboard Chef de Projet - En cours de développement',
    //     ]);
    // }


// This code is a Symfony controller for a dashboard that displays statistics and recent projects/tasks for users based on their role.
// It uses repositories to fetch data and renders a Twig template with the statistics and recent projects/tasks.
// src/Controller/DashboardController.php
// This file defines a controller for the dashboard of a Symfony application.
// It includes methods to fetch and display statistics about projects and tasks based on the user's role.


// {
//     #[Route('/dashboard', name: 'app_dashboard')]
//     public function index(): Response
//     {
//         return $this->render('dashboard/index.html.twig', [
//             'controller_name' => 'DashboardController',
//         ]);
//     }
// }
