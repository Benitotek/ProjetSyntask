<?php

namespace App\Security;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Migrations\Version\Version;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

// Version3.4 21/07/2025

/**
 * ProjectVoter
 *
 * This voter handles permissions for viewing, editing, deleting, and managing members of projects.
 * It checks if the user has the necessary roles and permissions based on their relationship to the project.
 */
class ProjectVoter extends Voter
{
    public const VIEW = 'PROJECT_VIEW';
    public const EDIT = 'PROJECT_EDIT';
    public const DELETE = 'PROJECT_DELETE';
    public const CREATE = 'PROJECT_CREATE';

    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE])
            && $subject instanceof Project;
    }

    /**
     * @param string $attribute
     * @param Project $project
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute($attribute, $project, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Les admins ou directeurs ont tous les droits
        if ($this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_DIRECTEUR')) {
            return true;
        }

        // Vérifier les autres rôles et relations
        switch ($attribute) {
            case self::VIEW:
                // Vérifier si l'utilisateur est chef de projet
                if ($project->getChefproject() === $user) {
                    return true;
                }
                // Vérifier si l'utilisateur est membre du projet
                if ($project->getMembres()->contains($user)) {
                    return true;
                }
                return false;
            case self::EDIT:
            case self::DELETE:
                // Seul le chef de projet peut modifier/supprimer
                return $project->getChefproject() === $user;
            case self::CREATE:
                // Vérifier si l'utilisateur a le rôle CHEF_PROJET
                return $this->security->isGranted('ROLE_CHEF_PROJET');
            default:
                return false;
        }
    }
    private function canEdit(Project $project, User $user): bool
    {
        // Seuls les chefs de projet peuvent éditer
        return $project->getChefProject() && $project->getChefProject()->getId() === $user->getId();
    }

    private function canDelete(Project $project, User $user): bool
    {
        // Seuls les chefs de projet peuvent supprimer
        return $project->getChefProject() && $project->getChefProject()->getId() === $user->getId();
    }

    private function canManageMembers(Project $project, User $user): bool
    {
        // Seuls les chefs de projet peuvent gérer les membres
        return $project->getChefProject() && $project->getChefProject()->getId() === $user->getId();
    }
}
