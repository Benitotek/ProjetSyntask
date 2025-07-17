<?php

namespace App\Controller;

use App\Repository\ActivityRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard')]
class ApiDashboardController extends AbstractController
{
    #[Route('/activity-data', name: 'api_dashboard_activity_data', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function activityData(
        ActivityRepository $activityRepository
    ): JsonResponse {
        // Exemple : total et type d’activités (à adapter)  
        $activityStats = $activityRepository->getStats(); // À implémenter dans ton repo si besoin  

        // Ex de retour  
        return $this->json([
            'success' => true,
            'stats' => $activityStats ?: [
                ['type' => 'task_comment', 'count' => 17],
                ['type' => 'task_create', 'count' => 4],
                ['type' => 'project_update', 'count' => 2],
            ]
        ]);
    }

    #[Route('/recent-activities', name: 'api_dashboard_recent_activities', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function recentActivities(
        ActivityRepository $activityRepository
    ): JsonResponse {
        // Récupère les 5 dernières activités  
        $activities = $activityRepository->findBy([], ['createdAt' => 'DESC'], 5);

        // Structure l’exemple de données  
        $data = [];
        foreach ($activities as $activity) {
            $data[] = [
                'id' => $activity->getId(),
                'type' => $activity->getType()->value,
                'description' => $activity->getDescription(),
                'user' => $activity->getUser()->getEmail(),
                'date' => $activity->getDateCreation()?->format('Y-m-d H:i'),
            ];
        }

        return $this->json([
            'success' => true,
            'activities' => $data,
        ]);
    }

    #[Route('/upcoming-due-dates', name: 'api_dashboard_upcoming_due_dates', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function upcomingDueDates(
        TaskRepository $taskRepository
    ): JsonResponse {
        // Exemple : taches à rendre dans 7 jours, à adapter  
        $upcomingTasks = $taskRepository->findUpcomingDueDatesForUser($this->getUser());

        $data = [];
        foreach ($upcomingTasks as $task) {
            $data[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'dueDate' => $task->getDueDate()?->format('Y-m-d'),
                'status' => $task->getStatus(),
            ];
        }

        return $this->json([
            'success' => true,
            'upcoming_due_dates' => $data,
        ]);
    }

    #[Route('/assigned-tasks', name: 'api_dashboard_assigned_tasks', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function assignedTasks(
        TaskRepository $taskRepository
    ): JsonResponse {
        // Exemple : récupère les tâches assignées à l’utilisateur actuel  
        $assignedTasks = $taskRepository->findAssignedToUser($this->getUser());

        $data = [];
        foreach ($assignedTasks as $task) {
            $data[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'project' => $task->getProject()?->getName(),
                'dueDate' => $task->getDueDate()?->format('Y-m-d'),
                'status' => $task->getStatus(),
            ];
        }

        return $this->json([
            'success' => true,
            'assigned_tasks' => $data,
        ]);
    }
}
