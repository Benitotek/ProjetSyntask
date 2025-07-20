<?php

namespace App\Enum;

enum TaskPriority: string
{
    case NORMAL = 'NORMAL';
    case URGENT = 'URGENT';
    case EN_ATTENTE = 'EN_ATTENTE';
    public function label(): string
    {
   
    
        return match ($this) {
            self::NORMAL => 'Normal',
            self::URGENT => 'Urgent',
            self::EN_ATTENTE => 'En attente',
        };
    }

}
