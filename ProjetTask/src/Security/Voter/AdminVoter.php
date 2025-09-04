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

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW_ALL_KANBAN,
            self::MANAGE_ALL_PROJECTS,
            self::MANAGE_ALL_USERS,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW_ALL_KANBAN,
            self::MANAGE_ALL_PROJECTS,
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
