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
        $userTasks = $taskRepository->findByAssignedUser($user);
        $stats['total_tasks'] = count($userTasks);
        $stats['pending_tasks'] = count(array_filter($userTasks, fn($t) => $t->getStatut() === 'EN-ATTENTE'));
        $stats['in_progress_tasks'] = count(array_filter($userTasks, fn($t) => $t->getStatut() === 'EN-COURS'));
        $stats['completed_tasks'] = count(array_filter($userTasks, fn($t) => $t->getStatut() === 'TERMINE'));
        $stats['overdue_tasks'] = $taskRepository->countOverdueByUser($user);

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            'recent_projects' => $recentProjects ?? [],
            'user_tasks' => array_slice($userTasks ?? [], 0, 10),
        ]);
    }
    // Ajoutez cette méthode pour créer la route manquante
    #[Route('/employe/dashboard', name: 'app_employe_dashboard')]
    public function employeDashboard(): Response
    {
        return $this->render('dashboard/employe_dashboard.html.twig', [
            'controller_name' => 'DashboardController',
        ]);
    }
}

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
