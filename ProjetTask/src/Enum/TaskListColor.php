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
            self::VERT => '#08da6dff',      // Vert clair
            self::JAUNE => '#f0ce46ff',     // Jaune clair
            self::ORANGE => '#ff951cff',    // Orange clair
            self::ROUGE => '#e43c3cff',     // Rouge clair
            self::BLEU => '#87b6f3ff',      // Bleu clair
        };
    }

    /**
     * Retourne la couleur CSS pour le texte
     */
    public function getTextColor(): string
    {
        return match ($this) {
            self::VERT => '#10b385ff',      // Vert foncé
            self::JAUNE => '#eadf1fff',     // Jaune foncé
            self::ORANGE => '#ea580c',    // Orange foncé
            self::ROUGE => '#cb3030ff',     // Rouge foncé
            self::BLEU => '#3b57b5ff',      // Bleu foncé
        };
    }
    public function css(): string
    {
        // Valeur hex utilisable en CSS
        return $this->value;
    }

    // Optionnel: helper si vous recevez un hex et voulez en faire un Enum
    public static function fromHex(string $hex): self
    {
        foreach (self::cases() as $c) {
            if (strcasecmp($c->value, $hex) === 0) {
                return $c;
            }
        }
        // Par défaut
        return self::BLEU;
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
        return match ($hexColor) {
            '#007bff' => self::BLEU,
            '#16cd6eff' => self::VERT,
            '#f3ca24ff' => self::JAUNE,
            '#fb941fff' => self::ORANGE,
            '#d62828ff' => self::ROUGE,
            '#6ba7f6ff' => self::BLEU,
            default => self::BLEU, // Fallback par défaut
        };
    }
}
