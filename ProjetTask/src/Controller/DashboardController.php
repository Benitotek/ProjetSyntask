<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\Attribute\Security;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    #[IsGranted("ROLE_EMPLOYE", message: "Accès réservé aux employés et administrateurs")]
    public function index(
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        UserRepository $userRepository
    ): Response {
        $user = $this->getUser();

        $stats = [
            'total_projects' => 0,
            'active_projects' => 0,
            'total_tasks' => 0,
            'pending_tasks' => 0,
            'in_progress_tasks' => 0,
            'completed_tasks' => 0,
            'overdue_tasks' => 0,
        ];

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            $stats['total_projects'] = $projectRepository->countAll();
            $stats['active_projects'] = $projectRepository->countByStatus(['EN-COURS']);
            $stats['total_users'] = $userRepository->countActive();
            $recentProjects = $projectRepository->findRecent(5);
        } elseif ($this->isGranted('ROLE_CHEF_DE_PROJET')) {
            $userProjects = $projectRepository->findByChefDeProjet($user);
            $stats['total_projects'] = count($userProjects);
            $stats['active_projects'] = count(array_filter($userProjects, fn($p) => $p->getStatut() === 'EN-COURS'));
            $recentProjects = array_slice($userProjects, 0, 5);
        } else {
            $userProjects = $projectRepository->findByAssignedUser($user);
            $stats['total_projects'] = count($userProjects);
            $recentProjects = array_slice($userProjects, 0, 5);
        }

        // Statistiques des tâches pour l'utilisateur
        if (method_exists($taskRepository, 'findByAssignedUser')) {
            $userTasks = $taskRepository->findByAssignedUser($user);
            $stats['total_tasks'] = count($userTasks);
            $stats['pending_tasks'] = count(array_filter($userTasks, fn($t) => $t->getStatut() === 'EN-ATTENTE'));
            $stats['in_progress_tasks'] = count(array_filter($userTasks, fn($t) => $t->getStatut() === 'EN-COURS'));
            $stats['completed_tasks'] = count(array_filter($userTasks, fn($t) => $t->getStatut() === 'TERMINE'));
            $stats['overdue_tasks'] = method_exists($taskRepository, 'countOverdueByUser') ? $taskRepository->countOverdueByUser($user) : 0;
        } else {
            $userTasks = [];
        }

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            'recent_projects' => $recentProjects ?? [],
            'user_tasks' => array_slice($userTasks ?? [], 0, 10),
        ]);
    }

    // Ajout des données nécessaires pour le template
    #[Route('/employe/dashboard', name: 'employe_dashboard')]
    #[IsGranted('ROLE_EMPLOYE')]
    public function employeDashboard(
        ProjectRepository $projectRepository,
        UserRepository $userRepository
    ): Response {
        $user = $this->getUser();

        // Récupérer un projet pour l'exemple (ou null)
        $project = null;
        if (method_exists($projectRepository, 'findByAssignedUser')) {
            $userProjects = $projectRepository->findByAssignedUser($user);
            $project = !empty($userProjects) ? $userProjects[0] : null;
        }

        // Récupérer tous les utilisateurs pour le select
        $users = $userRepository->findAll();

        return $this->render('dashboard/employe_dashboard.html.twig', [
            'project' => $project,
            'users' => $users,
            'controller_name' => 'DashboardController',
        ]);
    }

    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(): Response
    {
        return $this->render('dashboard/admin_dashboard.html.twig', [
            'controller_name' => 'DashboardController',
        ]);
    }

    #[Route('/directeur/dashboard', name: 'app_directeur_dashboard')]
    #[IsGranted('ROLE_DIRECTEUR')]
    public function directeurDashboardStats(ProjectRepository $projectRepository, UserRepository $userRepository): Response
    {
        $projects = $projectRepository->findAll();

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

        $percentActive = $totalProjects > 0 ? round(($activeProjects / $totalProjects) * 100) : 0;
        $percentCompleted = $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100) : 0;
        $percentPending = $totalProjects > 0 ? round(($pendingProjects / $totalProjects) * 100) : 0;

        $totalEmployees = $userRepository->count([]);

        $totalBudget = 0;
        foreach ($projects as $project) {
            if (method_exists($project, 'getBudget')) {
                $totalBudget += $project->getBudget() ?? 0;
            }
        }

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
        ]);
    }

    #[Route('/chef-de-projet/dashboard', name: 'app_chef_de_projet_dashboard')]
    #[IsGranted('ROLE_CHEF_DE_PROJET')]
    public function chefDeProjetDashboard(): Response
    {
        return $this->render('dashboard/chef_de_projet_dashboard.html.twig', [
            'controller_name' => 'DashboardController',
        ]);
    }

    #[Route('/chef-projet/dashboard', name: 'app_chef_projet_dashboard')]
    #[IsGranted('ROLE_CHEF_DE_PROJET')]
    public function chefProjetDashboard(): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'message' => 'Dashboard Chef de Projet - En cours de développement',
        ]);
    }
}

    
    // #[Route('/directeur/dashboard', name: 'app_directeur_dashboard')]
    // public function directeurDashboardStats(ProjectRepository $projectRepository, UserRepository $userRepository): Response
    // {
    //     // Récupérer tous les projets
    //     $projects = $projectRepository->findAll();
        
    //     // Calculer les statistiques
    //     $totalProjects = count($projects);
    //     $activeProjects = 0;
    //     $pendingProjects = 0;
    //     $completedProjects = 0;
        
    //     foreach ($projects as $project) {
    //         if ($project->getStatut() === 'EN-COURS') {
    //             $activeProjects++;
    //         } elseif ($project->getStatut() === 'TERMINE') {
    //             $completedProjects++;
    //         } else {
    //             $pendingProjects++;
    //         }
    //     }
        
    //     // Calculer les pourcentages
    //     $percentActive = $totalProjects > 0 ? round(($activeProjects / $totalProjects) * 100) : 0;
    //     $percentCompleted = $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100) : 0;
    //     $percentPending = $totalProjects > 0 ? round(($pendingProjects / $totalProjects) * 100) : 0;
        
    //     // Nombre total d'employés
    //     $totalEmployees = $userRepository->count([]);
        
    //     // Budget total (ceci est un exemple, adaptez selon votre modèle de données)
    //     $totalBudget = 0;
    //     foreach ($projects as $project) {
    //         // Assurez-vous que votre entité Project a un getter pour le budget
    //         // Si ce n'est pas le cas, vous pouvez omettre cette partie
    //         if (method_exists($project, 'getBudget')) {
    //             $totalBudget += $project->getBudget() ?? 0;
    //         }
    //     }
        
    //     // Statistiques pour le dashboard
    //     $stats = [
    //         'total_projects' => $totalProjects,
    //         'active_projects' => $activeProjects,
    //         'total_employees' => $totalEmployees,
    //         'total_budget' => $totalBudget,
    //         'percent_active' => $percentActive,
    //         'percent_completed' => $percentCompleted,
    //         'percent_pending' => $percentPending,
    //     ];
        
    //     return $this->render('dashboard/directeur_dashboard.html.twig', [
    //         'stats' => $stats,
    //         'projects' => $projects,
    //         // Si vous avez une entité Team, vous pouvez les ajouter ici
    //         // 'teams' => $teamRepository->findAll(),
    //     ]);
    // }

    // #[Route('/directeur/dashboard', name: 'app_directeur_dashboard')]
    // public function directeurDashboard(): Response
    // {
    //     return $this->render('dashboard/directeur_dashboard.html.twig', [
    //         'controller_name' => 'DashboardController',
    //     ]);
    // }
    // #[Route('/chef-de-projet/dashboard', name: 'app_chef_de_projet_dashboard')]
    // public function chefDeProjetDashboard(): Response
    // {
    //     return $this->render('dashboard/chef_de_projet_dashboard.html.twig', [
    //         'controller_name' => 'DashboardController',
    //     ]);
    // }
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
