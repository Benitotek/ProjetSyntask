<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class TaskCalendarService
{
    private TaskRepository $taskRepository;

    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    /**
     * Retourne les tâches du user courant (ou d'un projet) au format FullCalendar.
     * @param UserInterface $user
     * @param int|null $projectId
     * @return array
     */
    public function getUserCalendarTasks(UserInterface $user, ?int $projectId = null): array
    {
        // Si filtré par projet = récupère les tâches de ce projet assignées à ce user
        if ($projectId) {
            $tasks = $this->taskRepository->findByProjectAndUser($projectId, $user);
        } else {
            $tasks = $this->taskRepository->findAssignedToUser($user);
        }

        $calendarTasks = [];
        foreach ($tasks as $task) {
            // Format ISO 8601 pour FullCalendar
            $start = $task->getDateDebut() ? $task->getDateDebut()->format('Y-m-d\TH:i:s') : $task->getDateButoir()?->format('Y-m-d\TH:i:s');
            $end = $task->getDateButoir() ? $task->getDateButoir()->format('Y-m-d\TH:i:s') : null;

            // FullCalendar attend un tableau associatif par "event"
            $calendarTasks[] = [
                'id' => $task->getId(),
                'title' => $task->getTitre(),
                'start' => $start,
                'end' => $end,
                'url' => '/tasks/' . $task->getId(),
                'status' => $task->getStatut()?->value ?? null,
                'statusLabel' => $task->getStatut()?->getLabel() ?? '',
                'priority' => $task->getPriorite()?->value ?? null,
                'priorityLabel' => $task->getPriorite()?->getLabel() ?? '',
                'projectTitle' => $task->getProjet()?->getTitre(),
                'assignee' => [
                    'fullName' => $task->getAssignedUser()?->getFullName(),
                    'id' => $task->getAssignedUser()?->getId()
                ],
                'editable' => false, // tu peux rendre selon droits
                'description' => $task->getDescription() ?? '',
                // 'color' => ... (option, si tu veux colorier par statut/prio/etc.)
            ];
        }
        return $calendarTasks;
    }

    /**
     * Idem mais pour toutes les tâches d’un projet (pour le filtre)
     */
    public function getProjectCalendarTasks(UserInterface $user, int $projectId): array
    {
        // Option : sécurité, vérifier si le user peut voir le projet
        $tasks = $this->taskRepository->findByProjectAndUser($projectId, $user);
        $calendarTasks = [];
        foreach ($tasks as $task) {
            // même structure que précédemment
            $start = $task->getDateDebut() ? $task->getDateDebut()->format('Y-m-d\TH:i:s') : $task->getDateButoir()?->format('Y-m-d\TH:i:s');
            $end = $task->getDateButoir() ? $task->getDateButoir()->format('Y-m-d\TH:i:s') : null;
            $calendarTasks[] = [
                'id' => $task->getId(),
                'title' => $task->getTitre(),
                'start' => $start,
                'end' => $end,
                'url' => '/tasks/' . $task->getId(),
                'status' => $task->getStatut()?->value ?? null,
                'statusLabel' => $task->getStatut()?->getLabel() ?? '',
                'priority' => $task->getPriorite()?->value ?? null,
                'priorityLabel' => $task->getPriorite()?->getLabel() ?? '',
                'projectTitle' => $task->getProjet()?->getTitre(),
                'assignee' => [
                    'fullName' => $task->getAssignedUser()?->getFullName(),
                    'id' => $task->getAssignedUser()?->getId()
                ],
                'editable' => false,
                'description' => $task->getDescription() ?? '',
            ];
        }
        return $calendarTasks;
    }


    /**
     * Récupère les tâches au format calendrier pour un projet
     */


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
