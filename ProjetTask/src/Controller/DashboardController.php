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

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        UserRepository $userRepository
    ): Response {
        $user = $this->getUser();

        // Récupérer les données pour les statistiques
        $stats = [
            'projects' => [
                'total' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'overdue' => 0,
            ],
            'tasks' => [
                'total' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'overdue' => 0,
            ],
            'users' => [
                'total' => 0,
                'active' => 0,
            ]
        ];

        // Statistiques des projets
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            // Admin et directeurs voient toutes les statistiques
            $stats['projects']['total'] = $projectRepository->countAll();
            $stats['projects']['in_progress'] = $projectRepository->countBystatut(['EN-COURS']);
            $stats['projects']['completed'] = $projectRepository->countBystatut(['TERMINE']);

            // Statistiques des utilisateurs
            $stats['users']['total'] = $userRepository->countAll();
            $stats['users']['active'] = $userRepository->countActive();
        } elseif ($this->isGranted('ROLE_CHEF_PROJET')) {
            // Chefs de projet voient leurs projets
            $stats['projects']['total'] = count($projectRepository->findByChefDeProjet($user));
            // Ajouter d'autres statistiques spécifiques aux chefs de projet
        } else {
            // Employés voient leurs projets assignés
            $stats['projects']['total'] = count($projectRepository->findByMembre($user));
            // Ajouter d'autres statistiques pour les employés
        }

        // Statistiques des tâches (pour tous les utilisateurs)
        $stats['tasks']['overdue'] = count($taskRepository->findOverdue());

        // Si l'utilisateur est un employé, chercher ses tâches assignées
        if (!$this->isGranted('ROLE_CHEF_PROJET')) {
            $assignedTasks = $taskRepository->findByAssignedUser($user);
            $stats['tasks']['total'] = count($assignedTasks);
            // Ajouter d'autres statistiques de tâches
        }

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            // autres variables que vous pourriez déjà passer
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
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        $statut = $request->query->get('statut', 'tous');
        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'current_statut' => $statut,
            // autres variables...
        ]);

        // Différentes vues selon le rôle
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            // Vue administrateur/directeur
            return $this->adminDashboard($projectRepository, $taskRepository, $userRepository);
        } elseif ($this->isGranted('ROLE_CHEF_PROJET')) {
            // Vue chef de projet
            return $this->chefProjetDashboard($projectRepository, $taskRepository, $user);
        } else {
            // Vue employé
            return $this->employeDashboard($projectRepository, $taskRepository, $user);
        }
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


// This code is a Symfony controller for a dashboard that displays statistics and recent projects/tasks for users based on their roles.
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
