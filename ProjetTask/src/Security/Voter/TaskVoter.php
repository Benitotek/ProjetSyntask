<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Task;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Voter pour gérer les permissions des tâches
 */
class TaskVoter extends Voter
{
   
    public const VIEW = 'TASK_VIEW';
    public const EDIT = 'TASK_EDIT';
    public const DELETE = 'TASK_DELETE';
    public const CREATE = 'TASK_CREATE';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof Task) {
            return false;
        }

        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Les administrateurs et directeurs ont tous les droits
        if (in_array('ROLE_ADMIN', $user->getRoles()) || in_array('ROLE_DIRECTEUR', $user->getRoles())) {
            return true;
        }

        /** @var Task $task */
        $task = $subject;
        $project = $task->getTaskList()->getProject();

        // Vérifier si l'utilisateur est chef de projet
        if ($project->getChefproject() && $project->getChefproject()->getId() === $user->getId()) {
            return true;
        }

        // Cas spécifiques en fonction de l'attribut
        switch ($attribute) {
            case self::VIEW:
                // Les membres du projet peuvent voir les tâches
                return $project->getMembres()->contains($user);
                
            case self::EDIT:
            case self::DELETE:
                // Seul l'assigné peut modifier/supprimer sa tâche, en plus du chef de projet
                return $task->getAssignedUser() && $task->getAssignedUser()->getId() === $user->getId();
                
            case self::CREATE:
                // Les chefs de projet et membres peuvent créer des tâches
                return $project->getMembres()->contains($user);
        }

        return false;
    }
}
