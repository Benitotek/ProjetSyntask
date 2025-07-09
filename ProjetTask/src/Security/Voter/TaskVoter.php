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
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'TASK_DELETE';
    public const ASSIGN = 'TASK_ASSIGN';

    /**
     * Détermine si ce voter supporte l'attribut et le sujet donnés
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Si ce n'est pas un des attributs qu'on gère, retourner false
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::ASSIGN])) {
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

        // Les administrateurs et directeurs ont tous les droits
        $roles = method_exists($user, 'getRoles') ? $user->getRoles() : (array) $user->getRole();
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
            return true;
        }
        return match($attribute) {
            self::VIEW => $this->canView($task, $user),
            self::EDIT => $this->canEdit($task, $user),
            self::DELETE => $this->canDelete($task, $user),
            self::ASSIGN => $this->canAssign($task, $user),
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

        // Si la tâche fait partie d'un projet, l'utilisateur peut la voir s'il est membre ou chef du projet
        if ($task->getProject()) {
            $project = $task->getProject();
            if ($project->getChefProjet() === $user) {
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
        // L'utilisateur peut assigner la tâche s'il en est le créateur ou chef du projet
        if ($task->getCreatedBy() === $user) {
            return true;
        }
        if ($task->getProject() && $task->getProject()->getChefProjet() === $user) {
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
            ];
        }
    }    
                  