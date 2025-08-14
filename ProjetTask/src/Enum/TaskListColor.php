<?php

namespace App\Enum;

enum TaskListColor: string
{
    case VERT = 'VERT';      // Pas de retard
    case JAUNE = 'JAUNE';    // Retard léger (1-7 jours)
    case ORANGE = 'ORANGE';  // Retard moyen (8-30 jours)
    case ROUGE = 'ROUGE';    // Retard important (>30 jours)
    case BLEU = 'BLEU';      // Pas de date butoir définie

    /**
     * Retourne la couleur CSS correspondante
     */
    public function getCssColor(): string
    {
        return match ($this) {
            self::VERT => '#d1fae5',      // Vert clair
            self::JAUNE => '#fef3c7',     // Jaune clair
            self::ORANGE => '#fed7aa',    // Orange clair
            self::ROUGE => '#fecaca',     // Rouge clair
            self::BLEU => '#dbeafe',      // Bleu clair
        };
    }

    /**
     * Retourne la couleur CSS pour le texte
     */
    public function getTextColor(): string
    {
        return match ($this) {
            self::VERT => '#065f46',      // Vert foncé
            self::JAUNE => '#92400e',     // Jaune foncé
            self::ORANGE => '#ea580c',    // Orange foncé
            self::ROUGE => '#dc2626',     // Rouge foncé
            self::BLEU => '#1e40af',      // Bleu foncé
        };
    }

    /**
     * Calcule la couleur basée sur le retard en jours
     */
    public static function calculateByDelay(int $delayDays): self
    {
        return match (true) {
            $delayDays <= 0 => self::VERT,
            $delayDays <= 7 => self::JAUNE,
            $delayDays <= 30 => self::ORANGE,
            default => self::ROUGE,
        };
    }

    /**
     * Calcule la couleur basée sur les dates
     */
    public static function calculateByDates(?\DateTimeInterface $dateButoir, ?\DateTimeInterface $dateReelle = null): self
    {
        if (!$dateButoir) {
            return self::BLEU; // Pas de date butoir = BLEU
        }

        $dateComparison = $dateReelle ?? new \DateTime();
        $delay = $dateComparison->diff($dateButoir);
        $delayDays = $delay->invert ? $delay->days : -$delay->days;

        return self::calculateByDelay($delayDays);
    }

    /**
     * Retourne le label français
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::VERT => 'À temps',
            self::JAUNE => 'Retard léger',
            self::ORANGE => 'Retard moyen',
            self::ROUGE => 'Retard important',
            self::BLEU => 'Pas de date limite',
        };
    }

    /**
     * Retourne l'icône correspondante
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::VERT => '✅',
            self::JAUNE => '⚠️',
            self::ORANGE => '🔶',
            self::ROUGE => '🚨',
            self::BLEU => 'ℹ️',
        };
    }

    /**
     * Convertit une couleur hexadécimale en enum TaskListColor
     */
    public static function fromHexColor(string $hexColor): self
    {
        return match($hexColor) {
            '#007bff' => self::BLEU,
            '#d1fae5' => self::VERT,
            '#fef3c7' => self::JAUNE,
            '#fed7aa' => self::ORANGE,
            '#fecaca' => self::ROUGE,
            '#dbeafe' => self::BLEU,
            default => self::BLEU, // Fallback par défaut
        };
    }
}




// Autre version de l'énumération TaskListColor
// namespace App\Enum;
// enum TaskListColor: string
// {
//     case VERT = 'VERT';
//     case JAUNE = 'JAUNE';
//     case ORANGE = 'ORANGE';
//     case ROUGE = 'ROUGE';

//     public function getLabel(): string
//     {
//         return match ($this) {
//             self::VERT => 'À temps',
//             self::JAUNE => 'Retard léger',
//             self::ORANGE => 'Retard moyen',
//             self::ROUGE => 'Retard important',
//         };
//     }

//     public function getCssColor(): string
//     {
//         return match ($this) {
//             self::VERT => '#d1fae5',
//             self::JAUNE => '#fef3c7',
//             self::ORANGE => '#fed7aa',
//             self::ROUGE => '#fecaca',
//         };
//     }
// }




// TEST: src/Entity/Enum/TaskListColor.php(BUG mais a revoir pour les methodes de calcul de couleur et de date)
// namespace App\Enum;

// enum TaskListColor: string
// {
//     case VERT = 'VERT';      // Pas de retard
//     case JAUNE = 'JAUNE';    // Retard léger (1-7 jours)
//     case ORANGE = 'ORANGE';  // Retard moyen (8-30 jours)
//     case ROUGE = 'ROUGE';    // Retard important (>30 jours)

//     /**
//      * Retourne la couleur CSS correspondante
//      */
//     public function getCssColor(): string
//     {
//         return match ($this) {
//             self::VERT => '#d1fae5',      // Vert clair
//             self::JAUNE => '#fef3c7',     // Jaune clair
//             self::ORANGE => '#fed7aa',    // Orange clair
//             self::ROUGE => '#fecaca',     // Rouge clair
//         };
//     }

//     /**
//      * Retourne la couleur CSS pour le texte
//      */
//     public function getTextColor(): string
//     {
//         return match ($this) {
//             self::VERT => '#065f46',      // Vert foncé
//             self::JAUNE => '#92400e',     // Jaune foncé
//             self::ORANGE => '#ea580c',    // Orange foncé
//             self::ROUGE => '#dc2626',     // Rouge foncé
//         };
//     }

//     /**
//      * Calcule la couleur basée sur le retard en jours
//      */
//     public static function calculateByDelay(int $delayDays): self
//     {
//         return match (true) {
//             $delayDays <= 0 => self::VERT,
//             $delayDays <= 7 => self::JAUNE,
//             $delayDays <= 30 => self::ORANGE,
//             default => self::ROUGE,
//         };
//     }

//     /**
//      * Calcule la couleur basée sur les dates
//      */
//     public static function calculateByDates(?\DateTimeInterface $dateButoir, ?\DateTimeInterface $dateReelle = null): self
//     {
//         if (!$dateButoir) {
//             return self::VERT; // Pas de date butoir = pas de retard
//         }

//         $dateComparison = $dateReelle ?? new \DateTime();
//         $delay = $dateComparison->diff($dateButoir);
//         $delayDays = $delay->invert ? $delay->days : -$delay->days;

//         return self::calculateByDelay($delayDays);
//     }

//     /**
//      * Retourne le label français
//      */
//     public function getLabel(): string
//     {
//         return match ($this) {
//             self::VERT => 'À temps',
//             self::JAUNE => 'Retard léger',
//             self::ORANGE => 'Retard moyen',
//             self::ROUGE => 'Retard important',
//         };
//     }

//     /**
//      * Retourne l'icône correspondante
//      */
//     public function getIcon(): string
//     {
//         return match ($this) {
//             self::VERT => '✅',
//             self::JAUNE => '⚠️',
//             self::ORANGE => '🔶',
//             self::ROUGE => '🚨',
//         };
//     }
// }
