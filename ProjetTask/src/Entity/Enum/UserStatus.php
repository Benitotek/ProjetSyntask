<?php
namespace App\Enum;

enum UserStatus: string
{
    case ACTIF = 'ACTIF';
    case INACTIF = 'INACTIF';
    case EN_CONGE = 'EN_CONGE';
    case ABSENT = 'ABSENT';
}