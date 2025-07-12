<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\Task;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;


class NotificationService
{
    private EntityManagerInterface $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    /**
     * Crée une notification pour un utilisateur
     */
    public function createNotification(
        ?User $user,
        string $titre,
        string $message = null,
        string $lien = null,
        string $type = 'info'
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user)
            ->setTitre($titre)
            ->setMessage($message)
            ->setLien($lien)
            ->setType($type);
            
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
     * Notifie un utilisateur pour une assignation de tâche
     */
    public function notifyTaskAssignment(Task $task, User $user): void
    {
        $taskTitle = $task->getTitle();
        $titre = "Nouvelle tâche assignée";
        $message = "La tâche \"$taskTitle\" vous a été assignée.";
        $lien = "/task/{$task->getId()}";
        
        $this->createNotification($user, $titre, $message, $lien);
    }
    
    /**
     * Notifie un utilisateur pour un changement de statut de tâche
     */
    public function notifyTaskStatusChange(Task $task, string $oldStatus, string $newStatus): void
    {
        $taskTitle = $task->getTitle();
        $assignedUser = $task->getAssignedUser();
        
        if (!$assignedUser) {
            return;
        }
        
        $titre = "Statut de tâche modifié";
        $message = "La tâche \"$taskTitle\" est passée de \"$oldStatus\" à \"$newStatus\".";
        $lien = "/task/{$task->getId()}";
        
        $this->createNotification($assignedUser, $titre, $message, $lien, 'info');
    }
    
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
    public function markAllAsRead(User $user): void
    {
        $notifications = $this->entityManager->getRepository(Notification::class)
            ->findBy(['user' => $user, 'estLue' => false]);
        
        foreach ($notifications as $notification) {
            $notification->setEstLue(true);
        }
        
        $this->entityManager->flush();
    }
}