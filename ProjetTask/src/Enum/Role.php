<?php

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'ROLE_ADMIN';
    case DIRECTEUR = 'ROLE_DIRECTEUR';
    case  CHEF_PROJET = 'ROLE_ CHEF_PROJET';
    case EMPLOYE = 'ROLE_EMPLOYE';
    case MEMBRE = 'ROLE_MEMBRE';
    case USER = 'ROLE_USER';
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::DIRECTEUR => 'Directeur',
            self:: CHEF_PROJET => 'Chef de project',
            self::EMPLOYE => 'Employé',
            self::MEMBRE => 'Membre',
            self::USER => 'Utilisateur',
        };
    }
}
