<?php
// src/Service/ActivityLogger.php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\User;
use App\Enum\ActivityType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogger
{
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }
    //  * Enregistre une activité

    public function log(
        ActivityType $type,
        string $action,
        string $target,
        ?string $targetUrl = null,
        ?User $user = null
    ): void {
        // Si aucun utilisateur n'est fourni, utiliser l'utilisateur connecté
        if (!$user) {
            $user = $this->security->getUser();
            // Si aucun utilisateur n'est connecté, ne pas enregistrer d'activité
            if (!$user instanceof User) {
                return;
            }
        }

        $activity = new Activity();
        $activity->setUser($user);
        $activity->setType($type);
        $activity->setAction($action);
        $activity->setTarget($target);

        if ($targetUrl) {
            $activity->setTargetUrl($targetUrl);
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();
    }    
    /**
     * Log an activity for a user.
     *
     * @param \App\Entity\User|null $user
     * @param string $action
     * @param string $description
     * @param string $type
     * @param int|string|null $entityId
     */
    public function logActivity($user, string $action, string $description, string $type, $entityId = null): void
    {
         //Implement your logging logic here, e.g., save to database or file
         //Example:
         $activity = new Activity();
         $activity->setUser($user);
         $activity->setAction($action);
         $activity->setDescription($description);
         $activity->setType($type);
         $activity->setEntityId($entityId);
         $this->entityManager->persist($activity);
         $this->entityManager->flush();
    }

    /**
     * Méthodes pratiques pour les actions courantes
     */
    public function logProjectCreation(string $projectId, string $projectName, ?User $user = null): void
    {
        $this->log(
            ActivityType::PROJECT_CREATE,
            "a créé le project",
            $projectId,
            "/project/{$projectId}",
            $user
        );
    }

    public function logTaskCreation(string $taskId, string $taskTitle, ?User $user = null): void
    {
        $this->log(
            ActivityType::TASK_CREATE,
            "a créé la tâche",
            $taskId,
            "/task/{$taskId}",
            $user
        );
    }

    public function logTaskStatusChange(string $taskId, string $taskTitle, string $oldStatus, string $newStatus, ?User $user = null): void
    {
        $this->log(
            ActivityType::TASK_STATUS_CHANGE,
            "a changé le statut de '{$oldStatus}' à '{$newStatus}' pour la tâche",
            $taskId,
            "/task/{$taskId}",
            $user
        );
    }

public function logTaskAssignment(string $taskId, string $taskTitle, string $assignedToUsername, ?User $user = null): void
{
    $this->log(
        ActivityType::TASK_ASSIGN,
        "a assigné la tâche à {$assignedToUsername}",
        $taskId,
        "/task/{$taskId}",
        $user
    );
}

public function logTaskCompletion(string $taskId, string $taskTitle): void
{
    // Créez le message à enregistrer
    $message = sprintf("Tâche #%s (%s) terminée à %s\n", $taskId, $taskTitle, date('Y-m-d H:i:s'));

    // Chemin du fichier log
    $logFile = $this->getLogFilePath();

    // Écrire dans le fichier
    file_put_contents($logFile, $message, FILE_APPEND);
}

private function getLogFilePath(): string
{
    // Utiliser le répertoire de logs standard d'un projet Symfony
    return __DIR__ . '/../../var/log/task_activity.log';
}
    // Ajoutez d'autres méthodes spécifiques selon  besoins a voir dans le futur comment je développerais l'application
}
