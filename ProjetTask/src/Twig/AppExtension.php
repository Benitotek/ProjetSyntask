<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('getActivityIcon', [$this, 'getActivityIcon']),
        ];
    }

    public function getActivityIcon(string $type): string
    {
        // Logique pour déterminer quelle icône retourner selon le type d'activité
        return match (strtolower($type)) {
            'task-create', 'task_create' => 'plus-circle',
            'task-update', 'task_update' => 'edit',
            'task-complete', 'task_complete' => 'check-circle',
            'project-create', 'project_create' => 'folder-plus',
            'project-update', 'project_update' => 'folder-open',
            'project-complete', 'project_complete' => 'check-double',
            'comment' => 'comment',
            'user-join', 'user_join' => 'user-plus',
            default => 'bell',
        };
    }
}
