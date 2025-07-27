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

        // test pour voir soucis rajout temporraire d'un dump.
        dump($attribute, get_class($project), $user->getUserIdentifier(), $user->getRoles());
        dump($this->security->isGranted('ROLE_ADMIN'));

        if (!$user instanceof User) {
            return false;
        }

        // PRIORITÉ : Les admins ou directeurs voient/éditent TOUS les projets
        if (
            $this->security->isGranted('ROLE_ADMIN') ||
            $this->security->isGranted('ROLE_DIRECTEUR')
        ) {
            return true;
        }

        // Les chefs de projet et employés doivent être liés au projet
        switch ($attribute) {
            case self::VIEW:
            case self::EDIT:
            case self::DELETE:
                // Chef de projet ou membre assigné
                if ($project->getChefproject() === $user) {
                    return true;
                }
                if ($project->getMembres()->contains($user)) {
                    return true;
                }
                return false;
            case self::CREATE:
                // Règle si besoin : tous les chef projet peuvent créer, ou autre...
                return $this->security->isGranted('ROLE_CHEF_PROJET');
            default:
                return false;
        }
    }

    // Version 3.1+
    // const VIEW = 'PROJECT_VIEW';
    // const EDIT = 'PROJECT_EDIT';
    // const DELETE = 'PROJECT_DELETE';
    // const MANAGE_MEMBERS = 'PROJECT_MANAGE_MEMBERS';

    // private $security;

    // public function __construct(Security $security)
    // {
    //     $this->security = $security;
    // }
    // protected function supports(string $attribute, mixed $subject): bool
    // {
    //     // Si l'attribut n'est pas un de ceux qu'on gère, on ne vote pas
    //     if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])) {
    //         return false;
    //     }

    //     // On ne vote que pour les objets Project
    //     if (!$subject instanceof Project) {
    //         return false;
    //     }

    //     return true;
    // }

    // protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    // {
    //     $user = $token->getUser();

    //     // L'utilisateur doit être connecté

    //     if (!$user instanceof User) {
    //         return false;
    //     }

    // // Nouvel accès prioritaire pour les “grand rôles”
    // if (
    //     in_array('ROLE_ADMIN', $user->getRoles(), true) ||
    //     in_array('ROLE_DIRECTEUR', $user->getRoles(), true) ||
    //     in_array('ROLE_CHEF_PROJET', $user->getRoles(), true)
    // ) {
    //     return true;
    // }

    //     /** @var Project $project */
    //     $project = $subject;

    //     // Les administrateurs et directeurs ont tous les droits
    //     if ($this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_DIRECTEUR')) {
    //         return true;
    //     }

    //     // Vérification des droits selon l'attribut
    //     switch ($attribute) {
    //         case self::VIEW:
    //             return $this->canView($project, $user);
    //         case self::EDIT:
    //             return $this->canEdit($project, $user);
    //         case self::DELETE:
    //             return $this->canDelete($project, $user);
    //         case self::MANAGE_MEMBERS:
    //             return $this->canManageMembers($project, $user);
    //     }

    //     return false;
    // }

    // private function canView(Project $project, User $user): bool
    // {
    //     // Le chef de projet peut voir le projet
    //     if ($project->getChefProject() && $project->getChefProject()->getId() === $user->getId()) {
    //         return true;
    //     }

    //     // Les membres peuvent voir le projet
    //     foreach ($project->getMembres() as $membre) {
    //         if ($membre->getId() === $user->getId()) {
    //             return true;
    //         }
    //     }

    //     return false;
    // }
    //  // Vérifier si l'utilisateur est le créateur du projet
    //     $isCreator = $project->getCreatedBy()->getId() === $user->getId();

    //     // Vérifier si l'utilisateur est chef de projet ET membre du projet
    //     $isChefProjet = $this->security->isGranted('ROLE_CHEF_PROJET') && $project->isMembre($user);

    //     // Vérifier si l'utilisateur est simplement membre du projet
    //     $isMembre = $project->isMembre($user);

    //     switch ($attribute) {
    //         case self::VIEW:
    //             // Tous les membres du projet peuvent voir le projet
    //             return $isMembre;

    //         case self::EDIT:
    //             // Seuls le créateur et les chefs de projet membres peuvent modifier
    //             return $isCreator || $isChefProjet;

    //         case self::DELETE:
    //             // Seuls le créateur et les directeurs peuvent supprimer
    //             return $isCreator || $this->security->isGranted('ROLE_DIRECTEUR');
    //     }

    //     return false;
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
