<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ActivityRepository;
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
// use App\Repository\ActivityRepository; // Correction ici
// Assurez-vous que le namespace est correct pour votre projet
// Si vous avez un service spécifique pour les activités, utilisez-le
use App\Service\ActivityService; // Si vous avez un service pour les activités
use Container1mDkSxn\getActivityRepositoryService;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        UserRepository $userRepository,
        ActivityRepository $activityRepository // Correction ici
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

        // Récupérer les activités récentes
        $activities = $activityRepository->findRecent(10);

        // Calculs pour les statistiques
        $completedTasks = count(array_filter($tasks, function ($task) {
            return $task->getStatut() === 'TERMINE'; // Adapter selon votre structure
        }));

        $pendingTasks = count(array_filter($tasks, function ($task) {
            return $task->getStatut() === 'EN-ATTENTE'; // Adapter selon votre structure
        }));

        $inProgressTasks = count(array_filter($tasks, function ($task) {
            return $task->getStatut() === 'EN-COURS'; // Adapter selon votre structure
        }));
        // Si vous avez besoin d'une tâche spécifique, vous pouvez la récupérer ici
        // Par exemple, si vous voulez la première tâche ou une tâche spécifique
        // $task = $taskRepository->findOneBy(['someCondition' => 'value']);
// Récupérer une tâche valide ou créer un objet vide
    $task = $taskRepository->findOneBy([], ['id' => 'DESC']) ?? new Task();

$task = $taskRepository->findOneBy(['statut' => 'En cours']);

$tasks = $taskRepository->findBy([], ['dateCreation' => 'DESC'], 5);


        return $this->render('dashboard/index.html.twig', [
            'projects' => $projects,
            'tasks' => $tasks,
            'task' => $task, // Si vous avez une tâche spécifique à afficher
            'user' => $user,
            'current_statut' => 'dashboard', // Statut actuel pour le template
            'isAdmin' => $this->isGranted('ROLE_ADMIN'), // Vérification si l'utilisateur est admin
            'curent_statut' => 'dashboard', // Correction de la variable
            'tachesAssignees' => [], // Si vous avez des tâches assignées
            'userAssignees' => [], // Si vous avez des utilisateurs assignés
            'projectAssignees' => [], // Si vous avez des projets assignés
            'currentUser' => $user, // Utilisateur actuel
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

