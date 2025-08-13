<?php

namespace App\Enum;

enum TaskStatut: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case EN_COUR = 'EN_COUR';
    case EN_PAUSE = 'EN_PAUSE';
    case EN_REPRISE = 'EN_REPRISE';
    case TERMINER = 'TERMINER';
    case ANNULERR = 'ANNULERR';
    /**
     * Returns the label for the task statut.
     *
     * @return string The label corresponding to the task statut.
     */
    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::EN_COUR => 'En cours',
            self::EN_PAUSE => 'En pause',
            self::EN_REPRISE => 'En reprise',
            self::TERMINER => 'Terminé',
            self::ANNULERR => 'Annulé',
        };
    }
}
