<?php

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'ROLE_ADMIN';
    case DIRECTEUR = 'ROLE_DIRECTEUR';
    case CHEF_PROJET = 'ROLE_CHEF_PROJET';
    case EMPLOYE = 'ROLE_EMPLOYE';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::DIRECTEUR => 'Directeur',
            self::CHEF_PROJET => 'Chef de projet',
            self::EMPLOYE => 'Employé',
        };
    }
}
