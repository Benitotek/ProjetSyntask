<?php

namespace App\Security;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Migrations\Version\Version;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProjectVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';
    public const ASSIGN_TASKS = 'ASSIGN_TASKS';

    /**
     * Détermine si ce voter supporte l'attribut et le sujet donnés
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Si ce n'est pas un des attributs qu'on gère, retourner false
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::ASSIGN_TASKS])) {
            return false;
        }

        // N'accepter que les objets Project
        if (!$subject instanceof Project) {
            return false;
        }

        return true;
    }

    /**
     * Vérifie si l'utilisateur a le droit d'accéder au projet selon l'attribut donné
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être connecté
        if (!$user instanceof User) {
            return false;
        }

        /** @var Project $project */
        $project = $subject;

        // Les administrateurs et directeurs ont tous les droits
        if (in_array('ROLE_ADMIN', $user->getrole()) || in_array('ROLE_DIRECTEUR', $user->getrole())) {
            return true;
        }

        switch ($attribute) {
            case self::VIEW:
                // Les membres du projet peuvent le voir
                if ($project->getMembres()->contains($user)) {
                    return true;
                }

                // Le chef de projet peut voir le projet
                if ($project->getChef_Projet() === $user) {
                    return true;
                }

                break;

            case self::EDIT:
            case self::DELETE:
            case self::ASSIGN_TASKS:
                // Seul le chef de projet peut modifier/supprimer le projet ou assigner des tâches
                if ($project->getChef_Projet() === $user) {
                    return true;
                }

                break;
        }

        return false;
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

//         // Chef de projet peut voir ses projets
//         if (in_array('ROLE_CHEF_PROJET', $user->getrole()) && $project->getChef_Projet() === $user) {
//             return true;
//         }

//         // Employé peut voir les projets où il est membre
//         return $project->getMembres()->contains($user);
//     }

//     private function canEdit(Project $project, User $user): bool
//     {
//         // Admin peut tout éditer
//         if (in_array('ROLE_ADMIN', $user->getrole())) {
//             return true;
//         }

//         // Chef de projet peut éditer ses projets
//         if (in_array('ROLE_CHEF_PROJET', $user->getrole()) && $project->getChef_Projet() === $user) {
//             return true;
//         }

//         return false;
//     }
// }
// // This file is part of the Symfony project.