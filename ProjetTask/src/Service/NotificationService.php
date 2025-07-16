<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\Task;
use App\Entity\Project;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;
    private NotificationRepository $notificationRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        NotificationRepository $notificationRepository
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * Crée une notification pour un utilisateur
     */
    public function createNotification(
        User $user,
        string $titre,
        ?string $message = null,
        ?string $lien = null,
        string $type = 'info'
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user)
            ->setTitre($titre)
            ->setMessage($message)
            ->setLien($lien)
            ->setType($type)
            // ->setIconClass($iconClass ?? $this->getIconForType($type))
            ->setDateCreation(new \DateTime())
            ->setEstLue(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
    /**
     * Notifie un utilisateur pour la création d'un projet
     */
    public function notifyProjectCreation(Project $project, User $user): void
    {
        $titre = "Nouveau projet créé";
        $message = "Le projet \"{$project->getTitre()}\" a été créé.";
        $lien = "/project/{$project->getId()}";

        $this->createNotification($user, $titre, $message, $lien);
    }
    /**
     * Notifie la création d'une nouvelle tâche
     */
    public function notifyNewTask(Task $task): void
    {
        $assignedUser = $task->getAssignedUser();

        // Si la tâche est assignée et que l'utilisateur assigné n'est pas le créateur
        if ($assignedUser && $assignedUser !== $task->getCreatedBy()) {
            $taskUrl = $this->urlGenerator->generate('app_task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            $this->createNotification(
                $assignedUser,
                'Nouvelle tâche assignée',
                sprintf('"%s" vous a été assignée par %s', $task->getTitle(), $task->getCreatedBy()->getFullName()),
                'task_assigned',
                $taskUrl,
                'fa-tasks'
            );
        }
    }
    /**
     * Notifie un utilisateur pour une assignation de tâche
     */
    // public function notifyTaskAssignment(Task $task, User $user): void
    // {
    //     $taskTitle = $task->getTitle();
    //     $titre = "Nouvelle tâche assignée";
    //     $message = "La tâche \"$taskTitle\" vous a été assignée.";
    //     $lien = "/task/{$task->getId()}";

    //     $this->createNotification($user, $titre, $message, $lien);
    // }

    /**
     * Notifie l'assignation d'une tâche
     */
    public function notifyTaskAssignment(Task $task, User $previousAssignee = null): void
    {
        $assignedUser = $task->getAssignedUser();
        $creator = $task->getCreatedBy();

        // Si la tâche est assignée à un utilisateur différent du créateur
        if ($assignedUser && $assignedUser !== $creator) {
            $taskUrl = $this->urlGenerator->generate('app_task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            $this->createNotification(
                $assignedUser,
                'Tâche assignée',
                sprintf('La tâche "%s" vous a été assignée', $task->getTitle()),
                'task_assigned',
                $taskUrl,
                'fa-user-check'
            );
        }
    }
    /**
     * Notifie le changement de statut d'une tâche
     */
    // public function notifyStatusChange(Task $task, string $oldStatus): void
    // {
    //     $assignedUser = $task->getAssignedUser();
    //     $creator = $task->getCreatedBy();
    //     // $changedBy = $task->getUpdatedBy(); // L'utilisateur qui a changé le statut

    // Si la tâche est assignée et que l'utilisateur assigné n'est pas celui qui a changé le statut
    // if ($assignedUser && $assignedUser !== $changedBy) {
    //     $taskUrl = $this->urlGenerator->generate('app_task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

    //     $this->createNotification(
    //         $assignedUser,
    //         'Statut de tâche modifié',
    //         sprintf('Le statut de la tâche "%s" a été changé de "%s" à "%s"', 
    //             $task->getTitle(), 
    //             $this->getStatusLabel($oldStatus),
    //             $task->getStatusLabel()
    //         ),
    //         'status_change',
    //         $taskUrl,
    //         'fa-exchange-alt'
    //     );
    // }

    // Si le créateur n'est pas celui qui a changé le statut et n'est pas l'assigné
    // if ($creator !== $changedBy && $creator !== $assignedUser) {
    //     $taskUrl = $this->urlGenerator->generate('app_task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

    //     $this->createNotification(
    //         $creator,
    //         'Statut de tâche modifié',
    //         sprintf('Le statut de la tâche "%s" a été changé de "%s" à "%s"', 
    //             $task->getTitle(), 
    //             $this->getStatusLabel($oldStatus),
    //             $task->getStatusLabel()
    //         ),
    //         'status_change',
    //         $taskUrl,
    //         'fa-exchange-alt'
    //     );
    // }
    // }

    /**
     * Notifie l'ajout d'un commentaire sur une tâche
     */
    public function notifyNewComment(Task $task, User $commentAuthor): void
    {
        $usersToNotify = [];

        // Notifier le créateur de la tâche
        $creator = $task->getCreatedBy();
        if ($creator !== $commentAuthor) {
            $usersToNotify[] = $creator;
        }

        // Notifier l'utilisateur assigné
        $assignedUser = $task->getAssignedUser();
        if ($assignedUser && $assignedUser !== $commentAuthor && $assignedUser !== $creator) {
            $usersToNotify[] = $assignedUser;
        }

        // Envoyer les notifications
        if (!empty($usersToNotify)) {
            $taskUrl = $this->urlGenerator->generate('app_task_comments', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            foreach ($usersToNotify as $user) {
                $this->createNotification(
                    $user,
                    'Nouveau commentaire',
                    sprintf('%s a commenté la tâche "%s"', $commentAuthor->getFullName(), $task->getTitle()),
                    'new_comment',
                    $taskUrl,
                    'fa-comment'
                );
            }
        }
    }

    /**
     * Notifie l'approche de l'échéance d'une tâche
     */
    public function notifyTaskDueSoon(Task $task): void
    {
        $assignedUser = $task->getAssignedUser();

        if ($assignedUser) {
            $taskUrl = $this->urlGenerator->generate('app_task_show', ['id' => $task->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            $this->createNotification(
                $assignedUser,
                'Échéance proche',
                sprintf('La tâche "%s" arrive à échéance dans 2 jours', $task->getTitle()),
                'due_soon',
                $taskUrl,
                'fa-clock'
            );
        }
    }

    /**
     * Notifie l'invitation à un projet
     */
    public function notifyProjectInvitation(Project $project, User $invitedUser): void
    {
        $projectUrl = $this->urlGenerator->generate('app_project_show', ['id' => $project->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->createNotification(
            $invitedUser,
            'Invitation à un projet',
            sprintf(
                'Vous avez été invité au projet "%s" par %s',
                $project->getTitre(),
                $project->getCreatedBy()->getFullName()
            ),
            'project_invitation',
            $projectUrl,
            'fa-user-plus'
        );
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsRead(User $user): void
    {
        $notifications = $this->notificationRepository->findUnreadByUser($user);

        foreach ($notifications as $notification) {
            $notification->setEstLue(true);
        }

        $this->entityManager->flush();
    }

    /**
     * Nettoie les anciennes notifications lues
     */
    public function cleanupOldNotifications(): int
    {
        // Supprimer les notifications lues de plus de 30 jours
        $date = new \DateTime('-30 days');
        return $this->notificationRepository->deleteOldReadNotifications($date);
    }

    /**
     * Récupère le libellé d'un statut
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'A_FAIRE' => 'À faire',
            'EN_COURS' => 'En cours',
            'EN_REVUE' => 'En revue',
            'BLOQUEE' => 'Bloquée',
            'COMPLETEE' => 'Complétée',
            default => $status,
        };
    }

    /**
     * Récupère l'icône par défaut pour un type de notification
     */
    private function getIconForType(string $type): string
    {
        return match ($type) {
            'task_assigned' => 'fa-tasks',
            'status_change' => 'fa-exchange-alt',
            'new_comment' => 'fa-comment',
            'due_soon' => 'fa-clock',
            'project_invitation' => 'fa-user-plus',
            'task_completed' => 'fa-check-circle',
            'project_updated' => 'fa-project-diagram',
            default => 'fa-bell',
        };
    }
    //  * Notifie un utilisateur pour un changement de statut de tâche
    //  
    // public function notifyTaskStatusChange(Task $task, string $oldStatus, string $newStatus): void
    // {
    //     $taskTitle = $task->getTitle();
    //     $assignedUser = $task->getAssignedUser();

    //     if (!$assignedUser) {
    //         return;
    //     }

    //     $titre = "Statut de tâche modifié";
    //     $message = "La tâche \"$taskTitle\" est passée de \"$oldStatus\" à \"$newStatus\".";
    //     $lien = "/task/{$task->getId()}";

    //     $this->createNotification($assignedUser, $titre, $message, $lien, 'info');
    // }

    /**
     * Notifie les membres d'un projet pour une échéance proche
     */
    public function notifyUpcomingDeadline(Task $task, int $daysBeforeDeadline = 2): void
    {
        $dateButoir = $task->getDateButoir();

        if (!$dateButoir) {
            return;
        }

        $now = new \DateTime();
        $interval = $dateButoir->diff($now);

        // Ne notifier que si l'échéance est dans le nombre de jours spécifié
        if ($interval->days <= $daysBeforeDeadline && $interval->invert) {
            $taskTitle = $task->getTitle();
            $assignedUser = $task->getAssignedUser();

            if (!$assignedUser) {
                return;
            }

            $dateFormatted = $dateButoir->format('d/m/Y');
            $titre = "Échéance proche";
            $message = "La tâche \"$taskTitle\" doit être terminée d'ici le $dateFormatted.";
            $lien = "/task/{$task->getId()}";

            $this->createNotification($assignedUser, $titre, $message, $lien, 'warning');
        }
    }

    /**
     * Notifie pour les tâches en retard
     */
    public function notifyOverdueTask(Task $task): void
    {
        $dateButoir = $task->getDateButoir();

        if (!$dateButoir || $task->getStatut() === 'TERMINE') {
            return;
        }

        $now = new \DateTime();

        // Notifier uniquement si la tâche est en retard
        if ($dateButoir < $now) {
            $taskTitle = $task->getTitle();
            $assignedUser = $task->getAssignedUser();

            if (!$assignedUser) {
                return;
            }

            $dateFormatted = $dateButoir->format('d/m/Y');
            $titre = "Tâche en retard";
            $message = "La tâche \"$taskTitle\" devait être terminée le $dateFormatted.";
            $lien = "/task/{$task->getId()}";

            $this->createNotification($assignedUser, $titre, $message, $lien, 'error');
        }
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     */
    // public function markAllAsRead(User $user): void
    // {
    //     $notifications = $this->entityManager->getRepository(Notification::class)
    //         ->findBy(['user' => $user, 'estLue' => false]);

    //     foreach ($notifications as $notification) {
    //         $notification->setEstLue(true);
    //     }

    //     $this->entityManager->flush();
    // }
    /**
     * Supprimer les anciennes notifications
     */
    public function cleanOldNotifications(int $daysToKeep = 30): int
    {
        $date = new \DateTime();
        $date->modify('-' . $daysToKeep . ' days');

        return $this->notificationRepository->deleteOldReadNotifications($date);
    }
}
