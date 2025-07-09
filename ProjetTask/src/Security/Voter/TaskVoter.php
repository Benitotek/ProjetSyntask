<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Task;
use App\Entity\User;
/**
 * Voter pour gérer les permissions des tâches
 */
class TaskVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'TASK_DELETE';
    public const ASSIGN = 'TASK_ASSIGN';

    /**
     * Détermine si ce voter supporte l'attribut et le sujet donnés
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Si ce n'est pas un des attributs qu'on gère, retourner false
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::ASSIGN])) {
            return false;
        }

        // N'accepter que les objets Task
        if (!$subject instanceof Task) {
            return false;
        }

        return true;
    }

    /**
     * Vérifie si l'utilisateur a le droit d'accéder à la tâche selon l'attribut donné
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être connecté
        if (!$user instanceof User) {
            return false;
        }

        /** @var Task $task */
        $task = $subject;

        // Les administrateurs et directeurs ont tous les droits
        $roles = method_exists($user, 'getRoles') ? $user->getRoles() : (array) $user->getRole();
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
            return true;
        }
 return match($attribute) {
            self::VIEW => $this->canView($task, $user),
            self::EDIT => $this->canEdit($task, $user),
            self::DELETE => $this->canDelete($task, $user),
            self::ASSIGN => $this->canAssign($task, $user),
            default => false,

        };
         $project = $task->getProject();
    }
    /**
     * Vérifie si l'utilisateur peut voir la tâche  
     * @param Task $task
     * @param User $user
     * @return bool
     */
       private function canView(Task $task, User $user): bool
    {
        // L'utilisateur peut voir la tâche s'il en est le créateur ou l'assigné
        if ($task->getCreatedBy() === $user || $task->getAssignedUser() === $user) {
            return true;
        }

        // Si la tâche fait partie d'un projet, l'utilisateur peut la voir s'il est membre ou chef du projet
        if ($task->getProject()) {
            return $task->getProject()->getChefProjet() === $user || 
                  
        }

        return false;
    

        switch ($attribute) {
            case self::VIEW:
                // Les membres du projet peuvent voir les tâches
                if ($project->getMembres()->contains($user)) {
                    return true;
                }

                // Le chef de projet peut voir les tâches
                if ($project->getChefProjet() === $user) {
                    return true;
                }

                break;

            case self::EDIT:
            case self::DELETE:
                // Seul le chef de projet peut modifier/supprimer les tâches
                if ($project->getChefProjet() === $user) {
                    return true;
                }
                
                // Si la tâche est assignée à l'utilisateur, il peut la modifier
                if ($task->getAssignedUser() === $user) {
                    return true;
                }
                
                break;
            
            case self::ASSIGN:
                // Seul le chef de projet peut assigner des tâches
                if ($project->getChefProjet() === $user) {
                    return true;
                }
                                // Si l'utilisateur est un membre du projet, il peut être assigné
                                if ($project->getMembres()->contains($user)) {
                                    return true;
                                }
                            
                                break;
                        }
                
                        return false;
                    }
                }
            