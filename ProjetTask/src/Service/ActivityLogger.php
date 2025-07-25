<?php
// src/Service/ActivityLogger.php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ActivityType;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\SluggerInterface;

class ActivityLogger
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private RequestStack $requestStack;
    private SluggerInterface $slugger;
    /**
     * Constructeur
     */
    // Récupère les dépendances nécessaires
    public function __construct(EntityManagerInterface $entityManager, Security $security, RequestStack $requestStack, SluggerInterface $slugger)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->slugger = $slugger;
    }
    /**
     * Enregistre une activité dans un projet
     */
    public function logActivity(
        User $user,
        string $action,
        string $description,
        string $entityType,
        ?int $entityId = null,
        ?Project $project = null

    ): Activity {
        $activity = new Activity();
        $activity->setUser($user)
            ->setAction($action)
            ->setDescription($description)
            ->setDateCreation(new \DateTime())
            ->setType(ActivityType::from($entityType))
            ->setTarget($entityId);

        if ($project) {
            $activity->setProject($project);
        } else if ($entityType === 'project' && $entityId) {
            // Si c'est une activité sur un projet et qu'on n'a pas spécifié le projet
            $projectEntity = $this->entityManager->getRepository(Project::class)->find($entityId);
            if ($projectEntity) {
                $activity->setProject($projectEntity);
            }
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
    /**
     * Enregistre la création d'une tâche
     */
    public function logTaskCreation(User $user, string $taskTitle, int $taskId, Project $project): Activity
    {
        return $this->logActivity(
            $user,
            'a créé',
            'la tâche "' . $taskTitle . '"',
            'task',
            $taskId,
            $project
        );
    }

    /**
     * Enregistre la modification d'une tâche
     */
    public function logTaskUpdate(User $user, string $taskTitle, int $taskId, Project $project): Activity
    {
        return $this->logActivity(
            $user,
            'a modifié',
            'la tâche "' . $taskTitle . '"',
            'task',
            $taskId,
            $project
        );
    }

    /**
     * Enregistre le changement de statut d'une tâche
     */
    public function logTaskStatusChange(
        User $user,
        string $taskTitle,
        int $taskId,
        string $oldStatus,
        string $newStatus,
        Project $project
    ): Activity {
        return $this->logActivity(
            $user,
            'a changé le statut de',
            'la tâche "' . $taskTitle . '" de "' . $this->getStatusLabel($oldStatus) . '" à "' . $this->getStatusLabel($newStatus) . '"',
            'task_status',
            $taskId,
            $project
        );
    }

    /**
     * Enregistre l'assignation d'une tâche
     */
    public function logTaskAssignment(
        User $user,
        string $taskTitle,
        int $taskId,
        ?User $previousAssignee,
        User $newAssignee,
        Project $project
    ): Activity {
        $description = 'la tâche "' . $taskTitle . '"';

        if ($previousAssignee) {
            $description .= ' de ' . $previousAssignee->getPrenom() . ' ' . $previousAssignee->getNom();
        }

        $description .= ' à ' . $newAssignee->getPrenom() . ' ' . $newAssignee->getNom();

        return $this->logActivity(
            $user,
            'a assigné',
            $description,
            'task_assignment',
            $taskId,
            $project
        );
    }

    /**
     * Enregistre l'ajout d'un commentaire
     */
    public function logCommentAddition(User $user, string $taskTitle, int $taskId, Project $project): Activity
    {
        return $this->logActivity(
            $user,
            'a commenté',
            'la tâche "' . $taskTitle . '"',
            'comment',
            $taskId,
            $project
        );
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
     * Enregistre une activité dans la base de données
     *
     * @param ActivityType $type        Type d'activité
     * @param string       $description Description de l'activité
     * @param string       $entityId    ID de l'entité cible
     * @param string       $targetUrl   URL cible de l'activité
     * @param User|null    $user        Utilisateur qui a effectué l'activité
     */

    private function log(
        ActivityType $type,
        string $description,
        string $entityId,
        string $targetUrl,
        ?User $user = null
    ): void {
        $this->logActivity(
            $user,
            $description,
            $description,
            $type->value,
            $entityId,
           
        );
    }
    public function logProjectCreation(string $projectId, string $projectName, ?User $user = null): void
    {
        if (empty($projectId) || empty($projectName)) {
            throw new InvalidArgumentException("Project ID and name are required");
        }

        $this->log(
            ActivityType::PROJECT_CREATE,
            "a cree le projet",
            $projectId,
            "/project/{$projectId}",
            $user
        );
    }
}

    // public function logTaskStatusChange(string $taskId, string $taskTitle, string $oldStatus, string $newStatus, ?User $user = null): void
    // {
    //     $this->log(
    //         ActivityType::TASK_STATUS_CHANGE,
    //         "a changé le statut de '{$oldStatus}' à '{$newStatus}' pour la tâche",
    //         $taskId,
    //         "/task/{$taskId}",
    //         $user
    //     );
    // }

    // public function logTaskCompletion(string $taskId, string $taskTitle): void
    // {
    //     // Créez le message à enregistrer
    //     $message = sprintf("Tâche #%s (%s) terminée à %s\n", $taskId, $taskTitle, date('Y-m-d H:i:s'));

    //     // Chemin du fichier log
    //     $logFile = $this->getLogFilePath();

    //     // Écrire dans le fichier
    //     file_put_contents($logFile, $message, FILE_APPEND);
    // }

    // private function getLogFilePath(): string
    // {
    //     // Utiliser le répertoire de logs standard d'un projet Symfony
    //     return __DIR__ . '/../../var/log/task_activity.log';
    // }
