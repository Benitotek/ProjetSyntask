<?php

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Psr\Log\LoggerInterface;

class ProjectVoter extends Voter
{
    public const VIEW = 'PROJECT_VIEW';
    public const EDIT = 'PROJECT_EDIT';
    public const DELETE = 'PROJECT_DELETE';
    public const CREATE = 'PROJECT_CREATE';
    public const ARCHIVE = 'PROJECT_ARCHIVE';
    public function __construct(
        private Security $security,
        private LoggerInterface $logger
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return
            in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE, self::ARCHIVE], true)
            && $subject instanceof Project;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            $this->logger->warning('ProjectVoter: Utilisateur non authentifié');
            return false;
        }

        // Debug logging
        $this->logger->info('ProjectVoter: Vérification permission', [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'user_roles' => $user->getRoles(),
            'attribute' => $attribute,
            'project_id' => $subject->getId()
        ]);

        // SOLUTION: Vérification correcte des rôles admin/directeur
        $userRoles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $userRoles) || in_array('ROLE_DIRECTEUR', $userRoles)) {
            $this->logger->info('ProjectVoter: Accès accordé (ADMIN/DIRECTEUR)');
            return true;
        }
        $isArchived = $subject->isArchived() === true;
        if ($isArchived && $attribute !== self::ARCHIVE) {
            $this->logger->info('ProjectVoter: Accès refusé (projet archivé)');
            return false;
        }
        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            self::CREATE => $this->canCreate($user),
            self::ARCHIVE => $this->canArchived($subject, $user),
            default => false
        };
        switch ($attribute) {
            case self::VIEW:
                // Director, Manager of project, assigned member or owner can view
                if ($project->hasMember($user) || $project->isManager($user) || $user->hasRole('ROLE_DIRECTOR')) {
                    return true;
                }
                return false;

            case self::EDIT:
                if ($isArchived) {
                    return false;
                }
                // Manager of project or Director can edit
                return $project->isManager($user) || $user->hasRole('ROLE_DIRECTOR');

            case self::ARCHIVE:
                // Only Director or Admin can archive/unarchive
                return $user->hasRole('ROLE_DIRECTOR');
        }

        return false;
    }

    private function canArchived(Project $project, User $user): bool
    {
        // Only Director or Admin can archive/unarchive
        if (in_array('ROLE_DIRECTEUR', $user->getRoles()) || in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }
        // Chef de projet peut archiver son propre projet
        if ($project->getChefproject()?->getId() === $user->getId()) {
            return true;
        }
        return false;
    }

    private function canView(Project $project, User $user): bool
    {
        // Chef de projet
        if ($project->getChefproject()?->getId() === $user->getId()) {
            return true;
        }

        // Membre du projet
        return $project->getMembres()->contains($user);
    }

    private function canEdit(Project $project, User $user): bool
    {
        return $project->getChefproject()?->getId() === $user->getId();
    }

    private function canDelete(Project $project, User $user): bool
    {
        return $project->getChefproject()?->getId() === $user->getId();
    }

    private function canCreate(User $user): bool
    {
        return in_array('ROLE_CHEF_PROJET', $user->getRoles());
    }
}
