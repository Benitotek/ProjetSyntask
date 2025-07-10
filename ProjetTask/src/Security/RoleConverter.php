<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\UserRole;

class RoleConverter
{
    public function convertEnumToRoles(UserRole $role): array
    {
        return match ($role) {
            UserRole::ADMIN => ['ROLE_ADMIN', 'ROLE_DIRECTEUR', 'ROLE_CHEF_PROJET', 'ROLE_EMPLOYE', 'ROLE_USER'],
            UserRole::DIRECTEUR => ['ROLE_DIRECTEUR', 'ROLE_CHEF_PROJET', 'ROLE_EMPLOYE', 'ROLE_USER'],
            UserRole::CHEF_project => ['ROLE_CHEF_PROJET', 'ROLE_EMPLOYE', 'ROLE_USER'],
            UserRole::MEMBRE => ['ROLE_CHEF_PROJET', 'ROLE_EMPLOYE', 'ROLE_USER'],
            default => ['ROLE_USER'],
        };
    }
}
