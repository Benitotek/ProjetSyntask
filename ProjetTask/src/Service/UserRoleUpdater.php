<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;

class UserRoleUpdater
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function updateAllUserRoles(): int
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $users = $userRepository->findAll();
        $count = 0;

        foreach ($users as $user) {
            if ($this->synchronizeUserRole($user)) {
                $count++;
            }
        }

        $this->entityManager->flush();
        return $count;
    }

    public function synchronizeUserRole(User $user): bool
    {
        if (!$user->getStatut()) {
            return false;
        }

        $changed = false;
        $statut = $user->getStatut();
        $role = null;

        switch ($statut) {
            case UserStatus::ADMIN:
                $role = 'ROLE_ADMIN';
                break;
            case UserStatus::DIRECTEUR:
                $role = 'ROLE_DIRECTEUR';
                break;
            case UserStatus::CHEF_PROJET:
                $role = 'ROLE_CHEF_PROJET';
                break;
            case UserStatus::EMPLOYE:
                $role = 'ROLE_EMPLOYE';
                break;
            default:
                $role = 'ROLE_USER';
        }

        if ($user->getRole() !== $role) {
            $user->setRole($role);
            $this->entityManager->persist($user);
            $changed = true;
        }

        return $changed;
    }
}
