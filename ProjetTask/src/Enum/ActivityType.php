<?php
// src/Enum/ActivityType.php

namespace App\Enum;

enum ActivityType: string
{
    case PROJECT_CREATE = 'project_create';
    case PROJECT_UPDATE = 'project_update';
    case PROJECT_DELETE = 'project_delete';
    case PROJECT_ASSIGN = 'project_assign';


    case TASK_CREATE = 'task_create';
    case TASK_UPDATE = 'task_update';
    case TASK_DELETE = 'task_delete';
    case TASK_statut_CHANGE = 'task_statut_change';
    case TASK_ASSIGN = 'task_assign';
    case TASK_COMMENT = 'task_comment'; // ← ajoute cette ligne
    // ... autres cas


    case USER_LOGIN = 'user_login';
    case USER_REGISTER = 'user_register';

    case COMMENT_CREATE = 'comment_create';
    case COMMENT_UPDATE = 'comment_update';
    case COMMENT_DELETE = 'comment_delete';

    // Vous pouvez ajouter d'autres types d'activités selon vos besoins

    public function label(): string
    {
        return match ($this) {
            self::PROJECT_CREATE => 'Création de project',
            self::PROJECT_UPDATE => 'Mise à jour de project',
            self::PROJECT_DELETE => 'Suppression de project',
            self::PROJECT_ASSIGN => 'Affectation de project',

            self::TASK_CREATE => 'Création de tâche',
            self::TASK_UPDATE => 'Mise à jour de tâche',
            self::TASK_DELETE => 'Suppression de tâche',
            self::TASK_statut_CHANGE => 'Changement de statut de tâche',
            self::TASK_ASSIGN => 'Attribution de tâche',
            self::TASK_COMMENT => 'Commentaire sur la tâche', // �� ajoute cette ligne


            self::USER_LOGIN => 'Connexion utilisateur',
            self::USER_REGISTER => 'Inscription utilisateur',

            self::COMMENT_CREATE => 'Création de commentaire',
            self::COMMENT_UPDATE => 'Mise à jour de commentaire',
            self::COMMENT_DELETE => 'Suppression de commentaire',

            default => 'Activité inconnue',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PROJECT_CREATE, self::PROJECT_UPDATE, self::PROJECT_DELETE => 'fa-folder',
            self::TASK_CREATE, self::TASK_UPDATE, self::TASK_DELETE,
            self::TASK_statut_CHANGE, self::TASK_ASSIGN => 'fa-tasks',
            self::USER_LOGIN, self::USER_REGISTER => 'fa-user',
            self::COMMENT_CREATE => 'fa-comment',
            default => 'fa-info-circle',
        };
    }
}
