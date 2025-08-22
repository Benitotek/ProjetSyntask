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
            $start = $task->getdateCreation() ? $task->getDateReelle()->format('Y-m-d\TH:i:s') : $task->getDateButoir()?->format('Y-m-d\TH:i:s');
            $end = $task->getDateButoir() ? $task->getDateReelle()->format('Y-m-d\TH:i:s') : null;

            // FullCalendar attend un tableau associatif par "event"
            $calendarTasks[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'start' => $start,
                'end' => $end,
                'url' => '/tasks/' . $task->getId(),
                'statut' => $task->getStatut()?->value ?? null,
                'statutLabel' => $task->getStatut()?->label() ?? '',
                'priority' => $task->getPriorite()?->value ?? null,
                'priorityLabel' => $task->getPriorite()?->label() ?? '',
                'projectTitle' => $task->getProject()?->getTitre(),
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
            $start = $task->getDateCreation() ? $task->getDateReelle()->format('Y-m-d\TH:i:s') : $task->getDateButoir()?->format('Y-m-d\TH:i:s');
            $end = $task->getDateButoir() ? $task->getDateReelle()->format('Y-m-d\TH:i:s') : null;
            $calendarTasks[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'start' => $start,
                'end' => $end,
                'url' => '/tasks/' . $task->getId(),
                'statut' => $task->getStatut()?->value ?? null,
                'statutLabel' => $task->getStatut()?->label() ?? '',
                'priority' => $task->getPriorite()?->value ?? null,
                'priorityLabel' => $task->getPriorite()?->label() ?? '',
                'projectTitle' => $task->getProject()?->getTitre(),
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
                'start' => $task->getDateCreation() ? $task->getDateReelle()->format('Y-m-d') : null,
                'url' => '/task/' . $task->getId(),
                'backgroundColor' => $this->getColorForstatut($task->getstatut()),
                'borderColor' => $this->getColorForstatut($task->getstatut()),
                'textColor' => '#eb5a12ff',
                'description' => $task->getDescription() ? (strlen($task->getDescription()) > 100 ? substr($task->getDescription(), 0, 97) . '...' : $task->getDescription()) : '',
                'extendedProps' => [
                    'statut' => $task->getstatut(),
                    'statutLabel' => $task->getstatutLabel(),
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
    private function getColorForstatut(string $statut): string
    {
        return match ($statut) {
            'A_FAIRE' => '#6c757d',  // Gris
            'EN_COURS' => '#0d6efd', // Bleu
            'EN_REVUE' => '#ffc107', // Jaune
            'BLOQUEE' => '#dc3545',  // Rouge
            'COMPLETEE' => '#198754', // Vert
            default => '#6c757d',
        };
    }
    public function getAllCalendarTasks(): array
    {
        // Va chercher toutes les tasks en DB (pas filtrées par user/projet)
        $tasks = $this->taskRepository->findAll();

        $calendarTasks = [];
        foreach ($tasks as $task) {
            $start = $task->getDateCreation() ? $task->getDateReelle()->format('Y-m-d\TH:i:s') : $task->getDateButoir()?->format('Y-m-d\TH:i:s');
            $end   = $task->getDateButoir() ? $task->getDateReelle()->format('Y-m-d\TH:i:s') : null;

            $calendarTasks[] = [
                'id'           => $task->getId(),
                'title'        => $task->getTitle(),
                'start'        => $start,
                'end'          => $end,
                'url'          => '/tasks/' . $task->getId(),
                'statut'       => $task->getStatut()?->value ?? null,
                'statutLabel'  => $task->getStatut()?->label() ?? '',
                'priority'     => $task->getPriorite()?->value ?? null,
                'priorityLabel' => $task->getPriorite()?->label() ?? '',
                'projectTitle' => $task->getProject()?->getTitre(),
                'assignee'     => [
                    'fullName' => $task->getAssignedUser()?->getFullName(),
                    'id'       => $task->getAssignedUser()?->getId()
                ],
                'editable'     => false,
                'description'  => $task->getDescription() ?? '',
            ];
        }
        return $calendarTasks;
    }
    // ATTENTION: J'ai tester de changer url et j'ai mis les date debut en datecreation a revoir censé afficher 
    // tout (projet,taches,users) de tout les utilisateurs ayant roles employer
    //  mais visibles que pour admin 4 erreur a corriger au niveau des titres statut label etc


    // public function getAllEmployeeCalendarTasks(): array
    // { // Récupère toutes les tâches dont l’assigné a le rôle ROLE_EMPLOYEE
    //     $tasks = $this->taskRepository->findAllEmployeeTasks();

    //     $calendarTasks = [];
    //     foreach ($tasks as $task) {
    //         // Format ISO 8601 pour FullCalendar (ex: "2024-07-06T14:00:00")
    //         $start = $task->getDateCreation() ? $task->getDateCreation()->format('Y-m-d\TH:i:s') : $task->getDateButoir()?->format('Y-m-d\TH:i:s');
    //         $end   = $task->getDateButoir() ? $task->getDateButoir()->format('Y-m-d\TH:i:s') : null;

    //         $calendarTasks[] = [
    //             'id'           => $task->getId(),
    //             'title'        => $task->getTitre(),
    //             'start'        => $start,
    //             'end'          => $end,
    //             'url'          => '/admin/all/tasks' . $task->getId(),
    //             'statut'       => $task->getStatut()?->value ?? null,
    //             'statutLabel'  => $task->getStatut()?->getLabel() ?? '',
    //             'priority'     => $task->getPriorite()?->value ?? null,
    //             'priorityLabel' => $task->getPriorite()?->getLabel() ?? '',
    //             'projectTitle' => $task->getProject()?->getTitre(),
    //             'assignee'     => [
    //                 'fullName' => $task->getAssignedUser()?->getFullName(),
    //                 'id'       => $task->getAssignedUser()?->getId()
    //             ],
    //             'editable'     => false,
    //             'description'  => $task->getDescription() ?? '',
    //             // Option : tu peux ajouter 'color' pour colorier par statut/priorité
    //         ];
    //     }
    //     return $calendarTasks;
    // }
}
