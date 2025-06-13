<?php
namespace App\Enum;

enum UserStatus: string
{
    case ACTIF = 'ACTIF';
    case INACTIF = 'INACTIF';
    case EN_CONGE = 'EN_CONGE';
    case ABSENT = 'ABSENT';
    public function label(): string
    {
        return match ($this) {
            UserStatus::ACTIF => 'Actif',
            UserStatus::INACTIF => 'Inactif',
            UserStatus::EN_CONGE => 'En congé',
            UserStatus::ABSENT => 'Absent',
        };
    }
}