<?php

namespace App\Enum;

enum Userstatut: string
{
    case ACTIF = 'ACTIF';
    case INACTIF = 'INACTIF';
    case EN_CONGE = 'EN_CONGE';
    case ABSENT = 'ABSENT';

    public function label(): string
    {
        return match ($this) {
            Userstatut::ACTIF => 'Actif',
            Userstatut::INACTIF => 'Inactif',
            Userstatut::EN_CONGE => 'En congé',
            Userstatut::ABSENT => 'Absent',
            default => 'Statut inconnu',
        };
    }
}
