<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AdminVoter extends Voter
{
    public const VIEW_ALL_KANBAN = 'VIEW_ALL_KANBAN';
    public const MANAGE_ALL_PROJECTS = 'MANAGE_ALL_PROJECTS';
    public const MANAGE_ALL_USERS = 'MANAGE_ALL_USERS';
    public const ADMIN_KANBAN_VIEW = 'ADMIN_KANBAN_VIEW';
    public const PROJECT_ASSIGN = 'PROJECT_ASSIGN';
    public const USER_ASSIGN = 'USER_ASSIGN';
    public const USER_ASSIGN_TO_PROJECT = 'USER_ASSIGN_TO_PROJECT';
    public const USER_UNASSIGN_FROM_PROJECT = 'USER_UNASSIGN_FROM_PROJECT';
    public const USER_ASSIGN_TO_TASK = 'USER_ASSIGN_TO_TASK';
    public const USER_UNASSIGN_FROM_TASK = 'USER_UNASSIGN_FROM_TASK';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW_ALL_KANBAN,
            self::MANAGE_ALL_PROJECTS,
            self::MANAGE_ALL_USERS,
            self::ADMIN_KANBAN_VIEW,
            self::PROJECT_ASSIGN,
            self::USER_ASSIGN,
            self::USER_ASSIGN_TO_PROJECT,
            self::USER_UNASSIGN_FROM_PROJECT,
            self::USER_ASSIGN_TO_TASK,
            self::USER_UNASSIGN_FROM_TASK,

        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::ADMIN_KANBAN_VIEW => in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_DIRECTEUR', $user->getRoles(), true),
        self::PROJECT_ASSIGN => in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_DIRECTEUR', $user->getRoles(), true) || in_array('ROLE_CHEF_PROJET', $user->getRoles(), true),
            self::USER_ASSIGN => in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_DIRECTEUR', $user->getRoles(), true),
            self::USER_ASSIGN_TO_PROJECT => in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_DIRECTEUR', $user->getRoles(), true) || in_array('ROLE_CHEF_PROJET', $user->getRoles(), true),
            self::USER_UNASSIGN_FROM_PROJECT => in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_DIRECTEUR', $user->getRoles(), true) || in_array('ROLE_CHEF_PROJET', $user->getRoles(), true),
            self::USER_ASSIGN_TO_TASK => in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_DIRECTEUR', $user->getRoles(), true) || in_array('ROLE_CHEF_PROJET', $user->getRoles(), true),
            self::USER_UNASSIGN_FROM_TASK => in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_DIRECTEUR', $user->getRoles(), true) || in_array('ROLE_CHEF_PROJET', $user->getRoles(), true),
            self::VIEW_ALL_KANBAN => $this->canAccessAdminFeatures($user), 
            self::MANAGE_ALL_PROJECTS => $this->canAccessAdminFeatures($user),
            self::MANAGE_ALL_USERS => $this->canAccessAdminFeatures($user),
            default => false,
        };
    }

    private function canAccessAdminFeatures(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles()) ||
            in_array('ROLE_DIRECTEUR', $user->getRoles());

    }

}
