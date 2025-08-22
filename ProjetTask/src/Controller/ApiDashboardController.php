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
    // Cette méthode récupère les données de l'utilisateur connecté
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
// cette méthode récupère les 5 dernières activités
    #[Route('/recent-activities', name: 'api_dashboard_recent_activities', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function recentActivities(
        ActivityRepository $activityRepository
    ): JsonResponse {
        // Récupère les 5 dernières activités  
        $activities = $activityRepository->findBy([], ['dateCreation' => 'DESC'], 5);

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
                'statut' => $task->getstatut(),
            ];
        }

        return $this->json([
            'success' => true,
            'upcoming_due_dates' => $data,
        ]);
    }

    #[Route('/assigned-tasks', name: 'api_dashboard_assigned_tasks', methods: ['GET'])]
    public function assignedTasks(TaskRepository $taskRepository): JsonResponse
    {
        $tasks = $taskRepository->findAssignedToUser($this->getUser());
        $results = [];
        foreach ($tasks as $task) {
            $results[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'description' => $task->getDescription(),
                'assignedUser' => [
                    'prenom' => $task->getAssignedUser()?->getPrenom(),
                    'nom' => $task->getAssignedUser()?->getNom()
                ],
                'dueDate' => $task->getDateButoir()?->format('Y-m-d'),
                'statut' => $task->getStatut()?->value,
                'priority' => [
                    'value' => $task->getPriorite()?->value,
                    'label' => $task->getPriorite()?->label
                ]
            ];
        }
        return $this->json(['success' => true, 'assigned_tasks' => $results]);
    }
}
