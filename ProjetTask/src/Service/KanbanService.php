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
    ) {

    }

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

        // TODO: Émettre un Domain Event TaskMoved si système d'événements en place

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

        if ($task->getStatut() === TaskStatut::TERMINER) {
            return [
                'percentDone' => 100, // Example: 100% tasks completed
                'overdueCount' => 0, // Example: 0 overdue tasks
                'avgCycleTime' => '0 days', // Example: average cycle time of 0 days
            ];
        }


        // Example implementation for computing KPIs
        return [
            'percentDone' => 75, // Example: 75% tasks completed
            'overdueCount' => 3, // Example: 3 overdue tasks
            'avgCycleTime' => '2 days', // Example: average cycle time of 2 days
        ];
    }
    
}
