<?php
// src/Enum/ActivityType.php

namespace App\Enum;

enum ActivityType: string
{
    case PROJECT_CREATE = 'project_create';
    case PROJECT_UPDATE = 'project_update';
    case PROJECT_DELETE = 'project_delete';

    case TASK_CREATE = 'task_create';
    case TASK_UPDATE = 'task_update';
    case TASK_DELETE = 'task_delete';
    case TASK_STATUS_CHANGE = 'task_status_change';
    case TASK_ASSIGN = 'task_assign';
    case TASK_COMMENT = 'task_comment'; // ← ajoute cette ligne
    // ... autres cas


    case USER_LOGIN = 'user_login';
    case USER_REGISTER = 'user_register';

    case COMMENT_CREATE = 'comment_create';

    // Vous pouvez ajouter d'autres types d'activités selon vos besoins

    public function label(): string
    {
        return match ($this) {
            self::PROJECT_CREATE => 'Création de project',
            self::PROJECT_UPDATE => 'Mise à jour de project',
            self::PROJECT_DELETE => 'Suppression de project',

            self::TASK_CREATE => 'Création de tâche',
            self::TASK_UPDATE => 'Mise à jour de tâche',
            self::TASK_DELETE => 'Suppression de tâche',
            self::TASK_STATUS_CHANGE => 'Changement de statut de tâche',
            self::TASK_ASSIGN => 'Attribution de tâche',
            self::TASK_COMMENT => 'Commentaire sur la tâche', // �� ajoute cette ligne


            self::USER_LOGIN => 'Connexion utilisateur',
            self::USER_REGISTER => 'Inscription utilisateur',

            self::COMMENT_CREATE => 'Création de commentaire',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PROJECT_CREATE, self::PROJECT_UPDATE, self::PROJECT_DELETE => 'fa-folder',
            self::TASK_CREATE, self::TASK_UPDATE, self::TASK_DELETE,
            self::TASK_STATUS_CHANGE, self::TASK_ASSIGN => 'fa-tasks',
            self::USER_LOGIN, self::USER_REGISTER => 'fa-user',
            self::COMMENT_CREATE => 'fa-comment',
            default => 'fa-info-circle',
        };
    }
}
