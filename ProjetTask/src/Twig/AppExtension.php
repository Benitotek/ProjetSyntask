<?php

namespace App\Twig;

use App\Enum\ActivityType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{public function getFunctions(): array
    {
        return [
            new TwigFunction('getActivityIcon', [$this, 'getActivityIcon']),
        ];
    }

    public function getActivityIcon(string $type): string
    {
        // Convertir le type string en enum ActivityType si possible
        try {
            $enumType = ActivityType::from($type);
            return $enumType->icon();
        } catch (\ValueError $e) {
            // Fallback pour les anciens types qui ne correspondent pas à l'enum
            return match ($type) {
                'project_create', 'project_update', 'project_delete' => 'folder',
                'task_create', 'task_update', 'task_delete', 'task_status_change', 'task_assign' => 'tasks',
                'user_login', 'user_register' => 'user',
                'comment_create' => 'comment',
                default => 'info-circle',
            };
        }
    }
    // public function getFunctions()
    // {
    //     return [
    //         new TwigFunction('getActivityIcon', [$this, 'getActivityIcon']),
    //     ];
    // }

    // public function getActivityIcon(string $type): string
    // {
    //     // Logique pour déterminer quelle icône retourner selon le type d'activité
    //     return match (strtolower($type)) {
    //         'task-create', 'task_create' => 'plus-circle',
    //         'task-update', 'task_update' => 'edit',
    //         'task-complete', 'task_complete' => 'check-circle',
    //         'project-create', 'project_create' => 'folder-plus',
    //         'project-update', 'project_update' => 'folder-open',
    //         'project-complete', 'project_complete' => 'check-double',
    //         'comment' => 'comment',
    //         'user-join', 'user_join' => 'user-plus',
    //         default => 'bell',
    //     };
    // }
}
