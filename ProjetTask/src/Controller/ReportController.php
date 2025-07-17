<?php

namespace App\Controller;

use App\Enum\TaskStatut;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ReportController extends AbstractController
{
    #[Route('/reports/team', name: 'app_report_team')]
    #[IsGranted('ROLE_CHEF_PROJECT')]
    public function teamReport(
        Request $request,
        UserRepository $userRepository,
        TaskRepository $taskRepository,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository
    ): Response {
        // Récupération des utilisateurs (membres de l'équipe)
        $users = $userRepository->findAll();

        // Période de filtrage (peut être passée en paramètre)
        $period = $request->query->get('period', 'month');

        // Récupération du project si spécifié
        $projectId = $request->query->get('project');
        $project = null;
        if ($projectId) {
            $project = $projectRepository->find($projectId);
        }

        // Préparation des statistiques pour chaque membre de l'équipe
        $teamPerformance = [];

        foreach ($users as $teamMember) {
            // Recherche des tâches par utilisateur
            if ($project) {
                // Si un project est sélectionné, filtrer les tâches de ce project
                $userTasks = array_filter($taskRepository->findByAssignedUser($teamMember), function ($task) use ($project) {
                    return $task->getTaskList() && $task->getTaskList()->getProject() && $task->getTaskList()->getProject()->getId() === $project->getId();
                });
            } else {
                $userTasks = $taskRepository->findByAssignedUser($teamMember);
            }

            // Filtrer selon la période si nécessaire
            if ($period !== 'all') {
                $date = new \DateTime();
                switch ($period) {
                    case 'week':
                        $date->modify('-1 week');
                        break;
                    case 'month':
                        $date->modify('-1 month');
                        break;
                    case 'quarter':
                        $date->modify('-3 months');
                        break;
                }

                $userTasks = array_filter($userTasks, function ($task) use ($date) {
                    return $task->getDateCreation() >= $date;
                });
            }

            // Calcul des statistiques
            $userCompletedTasks = count(array_filter($userTasks, function ($task) {
                return $task->getStatut() === TaskStatut::TERMINE;
            }));

            $userOverdueTasks = count(array_filter($userTasks, function ($task) {
                return $task->getDateButoir() && $task->getDateButoir() < new \DateTime() && $task->getStatut() !== TaskStatut::TERMINE;
            }));

            // Recherche de la dernière activité
            $lastActivity = $activityRepository->findOneBy(
                ['user' => $teamMember],
                ['dateCreation' => 'DESC']
            );

            // Calcul de la vitesse moyenne de résolution (en jours)
            $resolutionSpeed = null;
            $completedTasksWithDates = array_filter($userTasks, function ($task) {
                return $task->getStatut() === TaskStatut::TERMINE && $task->getDateCreation() && $task->getDateCompletion();
            });

            if (count($completedTasksWithDates) > 0) {
                $totalDays = 0;
                foreach ($completedTasksWithDates as $task) {
                    $interval = $task->getDateCreation()->diff($task->getDateCompletion());
                    $totalDays += $interval->days;
                }
                $resolutionSpeed = $totalDays / count($completedTasksWithDates);
            }

            // Statistiques de performance par type de tâche
            $tasksByPriority = [
                'HAUTE' => 0,
                'MOYENNE' => 0,
                'BASSE' => 0
            ];

            foreach ($userTasks as $task) {
                if ($task->getPriorite()) {
                    $priority = $task->getPriorite()->value;
                    if (isset($tasksByPriority[$priority])) {
                        $tasksByPriority[$priority]++;
                    }
                }
            }

            $teamPerformance[] = [
                'user' => $teamMember,
                'assignedTasks' => count($userTasks),
                'completedTasks' => $userCompletedTasks,
                'completionRate' => count($userTasks) > 0 ? ($userCompletedTasks / count($userTasks)) * 100 : 0,
                'overdueTasks' => $userOverdueTasks,
                'lastActivity' => $lastActivity ? $lastActivity->getDateCreation() : null,
                'avgResolutionTime' => $resolutionSpeed,
                'tasksByPriority' => $tasksByPriority
            ];
        }

        // Tri des membres de l'équipe par taux de complétion (descendant)
        usort($teamPerformance, function ($a, $b) {
            return $b['completionRate'] <=> $a['completionRate'];
        });

        // Récupération des projects pour le filtre
        $projects = $projectRepository->findAll();

        return $this->render('report/team.html.twig', [
            'teamPerformance' => $teamPerformance,
            'projects' => $projects,
            'selectedProject' => $project,
            'period' => $period,
            'current_statut' => 'reports'
        ]);
    }
}
