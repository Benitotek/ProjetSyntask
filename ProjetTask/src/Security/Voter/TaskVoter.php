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
    const VIEW = 'VIEW';
    const EDIT = 'EDIT';
    const DELETE = 'TASK_DELETE';
    const ASSIGN = 'TASK_ASSIGN';
    const CHANGE_STATUS = 'CHANGE_STATUS';

    /**
     * Détermine si ce voter supporte l'attribut et le sujet donnés
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Si ce n'est pas un des attributs qu'on gère, retourner false
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::ASSIGN, self::CHANGE_STATUS])) {
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
        $project = $task->getProject();

        // Les administrateurs et directeurs ont tous les droits
        $roles = method_exists($user, 'getRoles') ? $user->getRoles() : (array) $user->getRole();
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
            return true;
        }
        return match ($attribute) {
            self::VIEW => $this->canView($task, $user),
            self::EDIT => $this->canEdit($task, $user),
            self::DELETE => $this->canDelete($task, $user),
            self::ASSIGN => $this->canAssign($task, $user),
            self::CHANGE_STATUS => $this->canChangeStatus($task, $user),
            default => false,
        };
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

        // Si la tâche fait partie d'un project, l'utilisateur peut la voir s'il est membre ou chef du project
        if ($task->getProject()) {
            $project = $task->getProject();
            if ($project->getChefproject() === $user) {
                return true;
            }
            if ($project->getMembres() && $project->getMembres()->contains($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut éditer la tâche
     * @param Task $task
     * @param User $user
     * @return bool
     */
    private function canEdit(Task $task, User $user): bool
    {
        // L'utilisateur peut éditer la tâche s'il en est le créateur ou l'assigné
        return $task->getCreatedBy() === $user || $task->getAssignedUser() === $user;
    }

    /**
     * Vérifie si l'utilisateur peut supprimer la tâche
     * @param Task $task
     * @param User $user
     * @return bool
     */
    private function canDelete(Task $task, User $user): bool
    {
        // L'utilisateur peut supprimer la tâche s'il en est le créateur
        return $task->getCreatedBy() === $user;
    }

    /**
     * Vérifie si l'utilisateur peut assigner la tâche
     * @param Task $task
     * @param User $user
     * @return bool
     */
    private function canAssign(Task $task, User $user): bool
    {
        // L'utilisateur peut assigner la tâche s'il en est le créateur ou chef du project
        if ($task->getCreatedBy() === $user) {
            return true;
        }
        if ($task->getProject() && $task->getProject()->getChefproject() === $user) {
            return true;
        }
        return false;
    }
    /**
     * Retourne les attributs gérés par ce voter
     * @return array<string>
     */
    public static function getSupportedAttributes(): array
    {
        return [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::ASSIGN,
            self::CHANGE_STATUS
        ];
    }
    /**
     * Retourne le nom du voter
     * @return string
     */
    public static function getVoterName(): string
    {
        return 'task_voter';
    }
    /**
     * Retourne les rôles requis pour chaque attribut
     * @return array<string, array<string>>
     */
    public static function getRequiredRoles(): array
    {
        return [
            self::VIEW => ['ROLE_USER'],
            self::EDIT => ['ROLE_USER'],
            self::DELETE => ['ROLE_USER'],
            self::ASSIGN => ['ROLE_USER'],
            self::CHANGE_STATUS => ['ROLE_USER'],
        ];
    }

    private function canChangeStatus(Task $task, User $user): bool


    {
        // L'utilisateur peut changer le statut de la tâche s'il en est le crianateur ou chef du project
        if ($task->getCreatedBy() === $user) {
            return true;
        }
        if ($task->getProject() && $task->getProject()->getChefproject() === $user) {
            return true;
        }
        // Récupérer le projet de la tâche
        $subject = $task->getProject();
        /** @var Project $project */
        $project = $subject;



        if (!$user instanceof User) {
            return false;
        }


        // Vérifier si l'utilisateur est le créateur du projet
        $isProjectCreator = $project->getCreatedBy() === $user;

        // Vérifier si l'utilisateur est chef du projet
        $isChefProjet = $project->getChefproject() === $user;

        // Vérifier si l'utilisateur est le créateur de la tâche
        $isTaskCreator = $task->getCreatedBy() === $user;

        // Vérifier si l'utilisateur est assigné à cette tâche
        $isAssignee = $task->getAssignedUser() === $user;

        // Vérifier si l'utilisateur est membre du projet
        $isProjectMember = $project->isMembre($user);

        // Retourner le résultat selon l'attribut
        // On utilise un switch pour éviter les conditions multiples
        // Les attributs gérés par ce voter sont VIEW, EDIT, DELETE, ASSIGN et CHANGE_STATUS
        // Les rôles requis pour chaque attribut sont décrits dans getRequiredRoles()
        // L'attribut CHANGE_STATUS est spécifique à notre application et n'est pas géré par ce voter directement
        // Cependant, on peut utiliser le switch pour éviter les conditions multiples et rendre le code plus concis et lisible
        // return match($attribute) {
        //     self::VIEW => $isProjectMember,
        //     self::EDIT => $isProjectCreator || $isChefProjet || $isTaskCreator || $isAssignee,
        //     self::DELETE => $isProjectCreator || $isChefProjet || $isTaskCreator,
        //     self::CHANGE_STATUS => $isProjectCreator || $isChefProjet || $isTaskCreator || $isAssignee,
        //     self::ASSIGN => $isProjectCreator || $isChefProjet,
        //     default => false,
        // };

        //     switch ($attribute) {

        //         case self::VIEW:
        //             // Tous les membres du projet peuvent voir la tâche
        //             return $isProjectMember;

        //         case self::EDIT:
        //             // Peuvent modifier: créateur du projet, chefs de projet, créateur de la tâche, assigné
        //             return $isProjectCreator || $isChefProjet || $isTaskCreator || $isAssignee;

        //         case self::DELETE:
        //             // Peuvent supprimer: créateur du projet, chefs de projet, créateur de la tâche
        //             return $isProjectCreator || $isChefProjet || $isTaskCreator;

        //         case self::CHANGE_STATUS:
        //             // Peuvent changer le statut: créateur du projet, chefs de projet, créateur de la tâche, assigné
        //             return $isProjectCreator || $isChefProjet || $isTaskCreator || $isAssignee;

        //         case self::ASSIGN:
        //             // Peuvent assigner: créateur du projet, chefs de projet
        //             return $isProjectCreator || $isChefProjet;
        //     }

        return false;
        // }

    }
}
