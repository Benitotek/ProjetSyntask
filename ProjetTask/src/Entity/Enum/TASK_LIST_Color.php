<?php

namespace App\Entity;

enum TASK_LIST_Color: string
{
    case URGENT = 'URGENT';
    case NORMAL = 'NORMAL';
    case EN_ATTENTE = 'EN-ATTENTE';
}