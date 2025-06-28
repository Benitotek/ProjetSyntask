<?php

namespace App\Enum;

enum UserStatus: string
{
    case ACTIF = 'ACTIF';
    case INACTIF = 'INACTIF';
    case EN_CONGE = 'EN_CONGE';
    case ABSENT = 'ABSENT';
    case CHEF_PROJET = 'chef_projet';
    case EMPLOYE = 'employe';
    case DIRECTEUR = 'directeur';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match ($this) {
            UserStatus::ACTIF => 'Actif',
            UserStatus::INACTIF => 'Inactif',
            UserStatus::EN_CONGE => 'En congé',
            UserStatus::ABSENT => 'Absent',
            UserStatus::CHEF_PROJET => 'Chef_Projet',
            UserStatus::EMPLOYE => 'Employé',
            UserStatus::DIRECTEUR => 'Directeur',
            UserStatus::ADMIN => 'Administrateur',
            default => 'Statut inconnu',
        };
    }
}
