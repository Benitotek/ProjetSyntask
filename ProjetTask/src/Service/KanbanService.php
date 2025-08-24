<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskList; // Column
use App\Entity\Project;
use App\Enum\TaskStatut;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

class KanbanService
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    /**
     * Déplace une tâche dans une colonne et position cible, en appliquant les règles métier
     * et en garantissant la densité des positions (0..N-1) via une transaction atomique.
     */
    public function moveTask(Task $task, TaskList $targetColumn, int $targetPosition): Task
    {
        $project = $task->getProject();
        if ($project?->isArchived() === true) {
            throw new RuntimeException('Le projet est archivé (lecture seule).');
        }

        $fromColumn = $task->getTaskList();
        $isFromDone = $this->isDoneColumn($fromColumn);
        $isToDone = $this->isDoneColumn($targetColumn);
        $isToInProgress = $this->isInProgressColumn($targetColumn);

        // Règles: Interdire quitter la colonne "Terminé"
        if ($isFromDone && $targetColumn->getId() !== $fromColumn->getId()) {
            throw new InvalidArgumentException('Impossible de sortir une tâche de la colonne Terminé.');
        }

        // Règles: Forcer EN_COURS en colonne "En cours"
        if ($isToInProgress) {
            $task->setStatut(TaskStatut::EN_COURS);
        }

        // Règles: Passage vers "Terminé" => assignedUser requis + dateFinReelle = now
        if ($isToDone) {
            if ($task->getAssignedUser() === null) {
                throw new InvalidArgumentException('Assigner la tâche avant de la passer en Terminé.');
            }
            $task->setStatut(TaskStatut::TERMINER);
            $task->setDateReelle(new \DateTime((new DateTimeImmutable())->format('Y-m-d H:i:s')));
        }

        // Déplacer la tâche
        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            // Retirer de l'ancienne colonne (recompacter)
            if ($fromColumn && $fromColumn->getId() !== $targetColumn->getId()) {
                $this->reindexPositions($fromColumn);
            }

            // Affecter la nouvelle colonne et insérer à targetPosition
            $task->setTaskList($targetColumn);
            $this->insertAtPosition($targetColumn, $task, $targetPosition);

            $this->em->flush();
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $this->em->refresh($task);

        return $task;
    }

    /**
     * Réordonne les colonnes pour un projet, positions denses 0..N-1
     */
    public function reorderColumns(Project $project, array $orderedColumnIds): void
    {
        if ($project->isArchived()) {
            throw new RuntimeException('Projet archivé.');
        }

        $columns = $project->getTaskLists(); // Assuming OneToMany ordered by position
        $map = [];
        foreach ($columns as $col) {
            $map[$col->getId()] = $col;
        }

        $position = 0;
        foreach ($orderedColumnIds as $cid) {
            if (!isset($map[$cid])) {
                throw new InvalidArgumentException("Colonne inconnue: $cid");
            }
            $map[$cid]->setPositionColumn($position++);
        }
        $this->em->flush();
    }

    private function insertAtPosition(TaskList $column, Task $task, int $targetPosition): void
    {
        $tasks = $column->getTasks(); // Collection
        $count = $tasks->count();

        if ($targetPosition < 0) $targetPosition = 0;
        if ($targetPosition > $count) $targetPosition = $count;

        // Décaler les tâches à partir de targetPosition
        foreach ($tasks as $t) {
            if ($t === $task) continue; // si même instance
            if ($t->getPosition() >= $targetPosition) {
                $t->setPosition($t->getPosition() + 1);
            }
        }
        $task->setPosition($targetPosition);
    }

    private function reindexPositions(TaskList $column): void
    {
        $tasks = $column->getTasks()->toArray();
        usort($tasks, fn(Task $a, Task $b) => $a->getPosition() <=> $b->getPosition());

        $i = 0;
        foreach ($tasks as $t) {
            $t->setPosition($i++);
        }
    }

    private function isDoneColumn(?TaskList $column): bool
    {
        if (!$column) return false;
        $name = mb_strtolower(trim($column->getNom()));
        return in_array($name, ['terminé', 'terminer', 'done', 'finished'], true);
    }

    private function isInProgressColumn(?TaskList $column): bool
    {
        if (!$column) return false;
        $name = mb_strtolower(trim($column->getNom()));
        return in_array($name, ['en cours', 'in progress', 'doing'], true);
    }
    public function computeKpis(Project $project, Task $task): array
    {
        if ($project->isArchived()) {
            throw new RuntimeException('Projet archivé.');
        }

        $isDone = $task->getStatut() === TaskStatut::TERMINER;
        $isOverdue = false;
        $today = new \DateTimeImmutable('today');

        if ($task->getDateButoir() instanceof \DateTimeInterface) {
            $isOverdue = !$isDone && $task->getDateButoir() < $today;
        }

        // Exemple de cycle time simple: dateCreation -> aujourd’hui ou dateReelle si done
        $cycleTimeDays = 0;
        $start = $task->getDateCreation() ?? null; // adaptez selon votre entité
        if ($start instanceof \DateTimeInterface) {
            $end = $isDone
                ? ($task->getDateReelle() ?? new \DateTimeImmutable())
                : new \DateTimeImmutable();
            $cycleTimeDays = max(0, (int) ceil(($end->getTimestamp() - $start->getTimestamp()) / 86400));
        }

        // Retour compatible avec agrégation
        return [
            'total' => 1,
            'done' => $isDone ? 1 : 0,
            'overdue' => $isOverdue ? 1 : 0,
            'cycleTimeDays' => $cycleTimeDays,
            // Si besoin, retournez des catégories par statut
            'cycleTimeDaysDone' => $isDone ? $cycleTimeDays : 0,
            'cycleTimeDaysInProgress' => (!$isDone && $task->getStatut() === TaskStatut::EN_COURS) ? $cycleTimeDays : 0,
            'cycleTimeDaysToDo' => (!$isDone && $task->getStatut() !== TaskStatut::EN_COURS) ? $cycleTimeDays : 0,
        ];
    }
    // ATTENTION cette méthode est commentée car etais dans le repository TaskListRepository
    // Elle a été déplacée dans ce service car elle concerne la logique métier.
    //Verifier si la methode moveTask au desssus a bien toutes les regles metier
    //sinon la supprimer pour eviter les doublons
    // /**

    /**
     * Déplace une tâche dans une autre colonne et position cible, en appliquant les règles métier
     * et en garantissant la densité des positions (0..N-1).
     *
     * @param Task $task La tâche à modifier
     * @param TaskList $targetColumn La colonne cible
     * @param int $targetPosition La position cible
     * @return Task La tâche modifiée
     * @throws InvalidArgumentException Si la position cible est invalide ou si la tâche est assignéee
     */
    // public function moveTask(Task $task, TaskList $targetColumn, int $targetPosition): Task
    // {
    //     $conn = $this->getEntityManager()->getConnection();
    //     $conn->beginTransaction();

    //     $fromColumn = $task->getTaskList();
    //     $isFromDone = $this->isDoneColumn($fromColumn);
    //     $isToDone = $this->isDoneColumn($targetColumn);
    //     $isToInProgress = $this->isInProgressColumn($targetColumn);

    //     // Règles: Interdire quitter la colonne "Terminé"
    //     if ($isFromDone && $targetColumn->getId() !== $fromColumn->getId()) {
    //         throw new InvalidArgumentException('Impossible de sortir une tâche de la colonne Terminé.');
    //     }

    //     // Règles: Forcer EN_COURS en colonne "En cours"
    //     if ($isToInProgress) {
    //         $task->setStatut(TaskStatut::EN_COURS);
    //     }

    //     // Règles: Passage vers "Terminé" => assignedUser requis + dateFinReelle = now
    //     if ($isToDone) {
    //         if ($task->getAssignedUser() === null) {
    //             throw new InvalidArgumentException('Assigner la tâche avant de la passer en Terminé.');
    //         }
    //         if ($task->getDateReelle() === null) {
    //             $task->setDateReelle(new \DateTime('now'));
    //         }
    //     }

    //     // Règles: Interdire passer en "Terminé" si dateFinReelle pas encore renseigné
    //     if ($isToDone && $task->getDateReelle() === null) {
    //         throw new InvalidArgumentException('Assigner la date de fin de la tâche avant de la passer en Terminé.');
    //     }

    //     // Vérifier la position cible
    //     if ($targetColumn->getId() === $fromColumn->getId() && $targetPosition === $task->getPosition()) {
    //         // Pas de changement nécessaire
    //         return $task;
    //     }

    //     if ($targetPosition < 0) {
    //         throw new InvalidArgumentException('La position cible doit être >= 0.');
    //     }

    //     // Ajuster les positions des tâches dans la colonne cible
    //     $this->adjustTargetColumnPositions($targetColumn, $targetPosition);

    //     // Déplacer la tâche
    //     $task->setTaskList($targetColumn);
    //     $task->setPosition($targetPosition);

    //     $this->getEntityManager()->persist($task);
    //     $this->getEntityManager()->flush();

    //     $conn->commit();
    //     return $task;
    // }


}
