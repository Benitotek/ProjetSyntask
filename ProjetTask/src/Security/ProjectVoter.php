<?php

namespace App\Security;

use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProjectVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT])
            && $subject instanceof Project;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        if (!$user instanceof User) {
            return false;
        }

        /** @var Project $project */
        $project = $subject;

        return match($attribute) {
            self::VIEW => $this->canView($project, $user),
            self::EDIT => $this->canEdit($project, $user),
            default => false,
        };
    }

    private function canView(Project $project, User $user): bool
    {
        // Admin et Directeur peuvent tout voir
        if (in_array('ROLE_ADMIN', $user->getRoles()) || in_array('ROLE_DIRECTEUR', $user->getRoles())) {
            return true;
        }

        // Chef de projet peut voir ses projets
        if (in_array('ROLE_CHEF_PROJET', $user->getRoles()) && $project->getChef_Projet() === $user) {
            return true;
        }

        // Employé peut voir les projets où il est membre
        return $project->getMembres()->contains($user);
    }

    private function canEdit(Project $project, User $user): bool
    {
        // Admin peut tout éditer
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Chef de projet peut éditer ses projets
        if (in_array('ROLE_CHEF_PROJET', $user->getRoles()) && $project->getChef_Projet() === $user) {
            return true;
        }

        return false;
    }
}
// This file is part of the Symfony project.