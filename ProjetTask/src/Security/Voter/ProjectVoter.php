<?php

namespace App\Security;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Migrations\Version\Version;
use Psr\Log\LoggerInterface;
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

    private Security $security;
    private LoggerInterface $logger;

    public function __construct(Security $security, LoggerInterface $logger)
    {
        $this->security = $security;
        $this->logger = $logger;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Si le sujet n'est pas un projet, on ne s'applique pas
        if (!$subject instanceof Project) {
            return false;
        }

        // Vérifier si l'attribut est l'un de ceux que nous supportons
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Assurez-vous que l'utilisateur est authentifié
        if (!$user instanceof User) {
            $this->logger->info('Utilisateur non authentifié');
            return false;
        }

        // Récupérer les rôles de l'utilisateur
        $roles = $user->getRoles();

        // Log pour débogage
        $this->logger->info('Vérification des permissions', [
            'user_email' => $user->getEmail(),
            'user_roles' => $roles,
            'attribute' => $attribute
        ]);

        // IMPORTANT: Vérifier d'abord si l'utilisateur est ADMIN ou DIRECTEUR
        // Ces utilisateurs ont un accès complet à tous les projets
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_DIRECTEUR', $roles)) {
            $this->logger->info('Accès accordé : utilisateur est ADMIN ou DIRECTEUR');
            return true;
        }

        /** @var Project $project */
        $project = $subject;

        // Traiter les autres cas en fonction de l'attribut
        switch ($attribute) {
            case self::VIEW:
                return $this->canView($project, $user);
            case self::EDIT:
                return $this->canEdit($project, $user);
            case self::DELETE:
                return $this->canDelete($project, $user);
            case self::CREATE:
                return $this->canCreate($user);
        }

        $this->logger->info('Accès refusé par défaut');
        return false;
    }

    private function canView(Project $project, User $user): bool
    {
        // Le chef de projet peut voir le projet
        if ($project->getChefproject() && $project->getChefproject()->getId() === $user->getId()) {
            return true;
        }

        // Les membres du projet peuvent le voir
        foreach ($project->getMembres() as $membre) {
            if ($membre->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    private function canEdit(Project $project, User $user): bool
    {
        // Seul le chef de projet peut l'éditer (dans le cas où ce n'est pas un admin ou directeur)
        return $project->getChefproject() && $project->getChefproject()->getId() === $user->getId();
    }

    private function canDelete(Project $project, User $user): bool
    {
        // Seul le chef de projet peut le supprimer (dans le cas où ce n'est pas un admin ou directeur)
        return $project->getChefproject() && $project->getChefproject()->getId() === $user->getId();
    }

    private function canCreate(User $user): bool
    {
        // Les chefs de projet peuvent créer des projets
        return in_array('ROLE_CHEF_PROJET', $user->getRoles());
    }
}
