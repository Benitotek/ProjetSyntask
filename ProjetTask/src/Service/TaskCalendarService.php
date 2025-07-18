<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Security\Core\Security;

class TaskCalendarService
{
    private TaskRepository $taskRepository;
    private SecurityBundle $security;

    public function __construct(TaskRepository $taskRepository, SecurityBundle $security)
    {
        $this->taskRepository = $taskRepository;
        $this->security = $security;
    }

    /**
     * Récupère les tâches au format calendrier pour un utilisateur
     */
    public function getUserCalendarTasks(User $user = null): array
    {
        if (!$user) {
            $user = $this->security->getuser();
            if (!$user instanceof User) {
                throw new \LogicException('Utilisateur non connecté');
            }
        }

        $tasks = $this->taskRepository->findByUserForCalendar($user);
        return $this->formatTasksForCalendar($tasks);
    }

    /**
     * Récupère les tâches au format calendrier pour un projet
     */
    public function getProjectCalendarTasks(int $projectId): array
    {
        $tasks = $this->taskRepository->findByProjectForCalendar($projectId);
        return $this->formatTasksForCalendar($tasks);
    }

    /**
     * Formate les tâches pour l'affichage dans le calendrier
     */
    private function formatTasksForCalendar(array $tasks): array
    {
        $calendarTasks = [];

        foreach ($tasks as $task) {
            $calendarTasks[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'start' => $task->getDateLimite() ? $task->getDateLimite()->format('Y-m-d') : null,
                'url' => '/task/' . $task->getId(),
                'backgroundColor' => $this->getColorForStatus($task->getStatus()),
                'borderColor' => $this->getColorForStatus($task->getStatus()),
                'textColor' => '#eb5a12ff',
                'description' => $task->getDescription() ? (strlen($task->getDescription()) > 100 ? substr($task->getDescription(), 0, 97) . '...' : $task->getDescription()) : '',
                'extendedProps' => [
                    'status' => $task->getStatus(),
                    'statusLabel' => $task->getStatusLabel(),
                    'priority' => $task->getPriority(),
                    'priorityLabel' => $task->getPriorityLabel(),
                    'projectId' => $task->getProject() ? $task->getProject()->getId() : null,
                    'projectTitle' => $task->getProject() ? $task->getProject()->getTitre() : null,
                    'assignee' => $task->getAssignedUser() ? [
                        'id' => $task->getAssignedUser()->getId(),
                        'fullName' => $task->getAssignedUser()->getFullName()
                    ] : null,
                ]
            ];
        }

        return $calendarTasks;
    }

    /**
     * Récupère la couleur associée à un statut
     */
    private function getColorForStatus(string $status): string
    {
        return match ($status) {
            'A_FAIRE' => '#6c757d',  // Gris
            'EN_COURS' => '#0d6efd', // Bleu
            'EN_REVUE' => '#ffc107', // Jaune
            'BLOQUEE' => '#dc3545',  // Rouge
            'COMPLETEE' => '#198754', // Vert
            default => '#6c757d',
        };
    }
}
