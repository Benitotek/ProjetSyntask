<?php

namespace App\Enum;

enum TaskListColor: string
{/**
 * Enum des couleurs standard des listes de tâches (Kanban).
 * - Backed enum en string pour un stockage clair et une sérialisation simple.
 * - Fournit des helpers pour labels, valeurs hex, et intégration Form/Twig.
 */


    case ORANGE = 'orange';
    case JAUNE  = 'yellow';
    case VERT   = 'green';
    case BLEU   = 'blue';
    case ROUGE  = 'red';
    case VIOLET = 'purple';
    case GRIS   = 'gray';

    /**
     * Libellé humain (i18n simplifiable via translator si nécessaire).
     */
    public function label(): string
    {
        return match ($this) {
            self::ORANGE => 'Orange',
            self::JAUNE  => 'Jaune',
            self::VERT   => 'Vert',
            self::BLEU   => 'Bleu',
            self::ROUGE  => 'Rouge',
            self::VIOLET => 'Violet',
            self::GRIS   => 'Gris',
        };
    }

    /**
     * Valeur hex recommandée pour l’UI.
     * Libre à vous d’ajuster la palette.
     */
    public function hex(): string
    {
        return match ($this) {
            self::ORANGE => '#f59e0b',
            self::JAUNE  => '#fbbf24',
            self::VERT   => '#10b981',
            self::BLEU   => '#3b82f6',
            self::ROUGE  => '#ef4444',
            self::VIOLET => '#8b5cf6',
            self::GRIS   => '#6b7280',
        };
    }

    /**
     * Retourne la liste des cases (utile en PHP < 8.2 pour compat).
     * Vous pouvez utiliser directement TaskListColor::cases() ailleurs.
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Récupère une case à partir d’un nom (ex: "VERT") ou d’une valeur (ex: "green").
     * Pratique pour assainir l’entrée venant d’un formulaire ou d’une query.
     */
    public static function fromNameOrValue(string $input): ?self
    {
        // Essayer par valeur d’abord (backed enum)
        foreach (self::cases() as $case) {
            if ($case->value === $input) {
                return $case;
            }
        }

        // Puis par nom (pour compat: "VERT", "BLEU", etc.)
        $upper = strtoupper($input);
        foreach (self::cases() as $case) {
            if ($case->name === $upper) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Retourne un tableau clé->valeur adapté à ChoiceType:
     * - clé (label affiché) => valeur (string) soumise,
     * - optionnellement, accès à la case pour customiser le label.
     *
     * Exemple d’usage:
     *   $builder->add('couleur', ChoiceType::class, [
     *     'choices' => TaskListColor::choicesForForm(),
     *   ]);
     */
    public static function choicesForForm(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value; // label => value
        }
        return $choices;
    }

    /**
     * Si vous désirez utiliser l’Enum comme objet Choice directement (sans value),
     * vous pouvez retourner:
     *
     *   public static function choiceObjects(): array
     *   {
     *       // 'choices' => TaskListColor::choiceObjects()
     *       // avec 'choice_value' et 'choice_label' adaptés
     *   }
     */

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
        $hexColor = strtolower($hexColor);
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
