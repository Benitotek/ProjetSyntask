<?php

namespace App\Security\Voter;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TaskVoter extends Voter
{
    public const VIEW = 'TASK_VIEW';
    public const EDIT = 'TASK_EDIT';
    public const DELETE = 'TASK_DELETE';
    public const CREATE = 'TASK_CREATE';

    public function __construct(private Security $security) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Task &&
            in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Admin et directeur ont tous les droits
        if (
            in_array('ROLE_ADMIN', $user->getRoles()) ||
            in_array('ROLE_DIRECTEUR', $user->getRoles())
        ) {
            return true;
        }

        /** @var Task $task */
        $task = $subject;
        $project = $task->getTaskList()->getProject();

        // Chef de projet
        if ($project->getChefproject()?->getId() === $user->getId()) {
            return true;
        }

        return match ($attribute) {
            self::VIEW, self::CREATE => $project->getMembres()->contains($user),
            self::EDIT, self::DELETE => $task->getAssignedUser()?->getId() === $user->getId(),
            default => false
        };
    }
}
