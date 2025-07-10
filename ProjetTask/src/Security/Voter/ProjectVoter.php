<?php

namespace App\Security;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Migrations\Version\Version;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

// Version3 debut 10/07/2025

/**
 * ProjectVoter
 *
 * This voter handles permissions for viewing, editing, deleting, and managing members of projects.
 * It checks if the user has the necessary roles and permissions based on their relationship to the project.
 */
class ProjectVoter extends Voter
{
    const VIEW = 'PROJECT_VIEW';
    const EDIT = 'PROJECT_EDIT';
    const DELETE = 'PROJECT_DELETE';
    const MANAGE_MEMBERS = 'PROJECT_MANAGE_MEMBERS';

    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::MANAGE_MEMBERS
        ]) && $subject instanceof Project;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Project $project */
        $project = $subject;

        // Les administrateurs et directeurs ont tous les droits
        if ($this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_DIRECTEUR')) {
            return true;
        }

        // Vérification des droits selon l'attribut
        switch ($attribute) {
            case self::VIEW:
                return $this->canView($project, $user);
            case self::EDIT:
                return $this->canEdit($project, $user);
            case self::DELETE:
                return $this->canDelete($project, $user);
            case self::MANAGE_MEMBERS:
                return $this->canManageMembers($project, $user);
        }

        return false;
    }

    private function canView(Project $project, User $user): bool
    {
        // Le chef de projet peut voir le projet
        if ($project->getChefproject() && $project->getChefproject()->getId() === $user->getId()) {
            return true;
        }

        // Les membres peuvent voir le projet
        foreach ($project->getMembres() as $membre) {
            if ($membre->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    private function canEdit(Project $project, User $user): bool
    {
        // Seuls les chefs de projet peuvent éditer
        return $project->getChefproject() && $project->getChefproject()->getId() === $user->getId();
    }

    private function canDelete(Project $project, User $user): bool
    {
        // Seuls les chefs de projet peuvent supprimer
        return $project->getChefproject() && $project->getChefproject()->getId() === $user->getId();
    }

    private function canManageMembers(Project $project, User $user): bool
    {
        // Seuls les chefs de projet peuvent gérer les membres
        return $project->getChefproject() && $project->getChefproject()->getId() === $user->getId();
    }
}

// Version 1 avnt 02/07/2025
//     public const VIEW = 'view';
//     public const EDIT = 'edit';

//     protected function supports(string $attribute, mixed $subject): bool
//     {
//         return in_array($attribute, [self::VIEW, self::EDIT])
//             && $subject instanceof Project;
//     }

//     protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
//     {
//         $user = $token->getUser();
        
//         if (!$user instanceof User) {
//             return false;
//         }

//         /** @var Project $project */
//         $project = $subject;

//         return match($attribute) {
//             self::VIEW => $this->canView($project, $user),
//             self::EDIT => $this->canEdit($project, $user),
//             default => false,
//         };
//     }

//     private function canView(Project $project, User $user): bool
//     {
//         // Admin et Directeur peuvent tout voir
//         if (in_array('ROLE_ADMIN', $user->getrole()) || in_array('ROLE_DIRECTEUR', $user->getrole())) {
//             return true;
//         }

//         // Chef de project peut voir ses projects
//         if (in_array('ROLE_CHEF_project', $user->getrole()) && $project->getChef_project() === $user) {
//             return true;
//         }

//         // Employé peut voir les projects où il est membre
//         return $project->getMembres()->contains($user);
//     }

//     private function canEdit(Project $project, User $user): bool
//     {
//         // Admin peut tout éditer
//         if (in_array('ROLE_ADMIN', $user->getrole())) {
//             return true;
//         }

//         // Chef de project peut éditer ses projects
//         if (in_array('ROLE_CHEF_project', $user->getrole()) && $project->getChef_project() === $user) {
//             return true;
//         }

//         return false;
//     }
// }
// // This file is part of the Symfony project.