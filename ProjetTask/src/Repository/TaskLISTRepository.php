<?php

namespace App\Repository;

use App\Entity\TaskList;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\TaskListColor;
use InvalidArgumentException;
use App\Entity\Task;
use App\Enum\TaskStatut;

class TaskListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskList::class);
    }

    /**
     * Retourne les colonnes d'un projet avec leurs tâches (fetch-join),
     * ordonnées par position de colonne puis position de tâche.
     *
     * @return TaskList[]
     */
    public function findByProjectWithTasksOrdered(Project $project): array
    {
        return $this->createQueryBuilder('tl')
            ->leftJoin('tl.tasks', 't')->addSelect('t')
            ->andWhere('tl.project = :p')->setParameter('p', $project)
            ->orderBy('tl.positionColumn', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }


    /**
     * Trouve la position maximale des colonnes d'un project
     * 
     * @param Project $project Le project concerné
     * @return int La position maximale
     */
    public function findMaxPositionByProject(Project $project): int
    {
        $result = $this->createQueryBuilder('tl')
            ->select('MAX(tl.positionColumn)')
            ->where('tl.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?: 0;
    }
    public function findLastPositionForProject(Project $project): int
    {
        return $this->createQueryBuilder('t')
            ->select('MAX(t.positionColumn)')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult() ?? 0; // retourne 0 si aucune colonne
    }
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
    public function moveTask(Task $task, TaskList $targetColumn, int $targetPosition): Task
    {
        $conn = $this->getEntityManager()->getConnection();
        $conn->beginTransaction();

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
            $task->setStatut(TaskStatut::EN_COUR);
        }

        // Règles: Passage vers "Terminé" => assignedUser requis + dateFinReelle = now
        if ($isToDone) {
            if ($task->getAssignedUser() === null) {
                throw new InvalidArgumentException('Assigner la tâche avant de la passer en Terminé.');
            }
            if ($task->getDateReelle() === null) {
                $task->setDateReelle(new \DateTime('now'));
            }
        }

        // Règles: Interdire passer en "Terminé" si dateFinReelle pas encore renseigné
        if ($isToDone && $task->getDateReelle() === null) {
            throw new InvalidArgumentException('Assigner la date de fin de la tâche avant de la passer en Terminé.');
        }

        // Vérifier la position cible
        if ($targetColumn->getId() === $fromColumn->getId() && $targetPosition === $task->getPosition()) {
            // Pas de changement nécessaire
            return $task;
        }

        if ($targetPosition < 0) {
            throw new InvalidArgumentException('La position cible doit être >= 0.');
        }

        // Ajuster les positions des tâches dans la colonne cible
        $this->adjustTargetColumnPositions($targetColumn, $targetPosition);

        // Déplacer la tâche
        $task->setTaskList($targetColumn);
        $task->setPosition($targetPosition);

        $this->em->persist($task);
        $this->em->flush();

        $conn->commit();
        return $task;
    }

    /**
     * Réorganise les positions des colonnes d'un project
     * 
     * @param Project $project Le project concerné
     */
    public function reorganizePositions(Project $project): void
    {
        $taskLists = $this->findBy(['project' => $project], ['positionColumn' => 'ASC']);

        $entityManager = $this->getEntityManager();
        $position = 1;

        foreach ($taskLists as $taskList) {
            $taskList->setPositionColumn($position++);
        }

        $entityManager->flush();
    }

    /**
     * Réordonne les colonnes selon un tableau de positions
     * 
     * @param Project $project Le project concerné
     * @param array $columns Tableau associatif [id => position]
     */
    public function reorderColumns(Project $project, array $columns): void
    {
        $entityManager = $this->getEntityManager();

        foreach ($columns as $columnData) {
            if (isset($columnData['id'], $columnData['position'])) {
                $taskList = $this->find($columnData['id']);

                if ($taskList && $taskList->getProject() === $project) {
                    $taskList->setPositionColumn((int)$columnData['position']);
                }
            }
        }

        $entityManager->flush();
    }

    /**
     * Met à jour automatiquement les couleurs des colonnes selon leur position
     * 
     * @param Project $project Le project concerné
     */
    public function updateAutoColorsForProject(Project $project): void
    {
        $entityManager = $this->getEntityManager();
        $taskLists = $this->findBy(['project' => $project], ['positionColumn' => 'ASC']);

        // Couleurs prédéfinies pour les colonnes par défaut
        $defaultColors = [
            'À faire' => TaskListColor::ORANGE,
            'En cours' => TaskListColor::JAUNE,
            'Terminé' => TaskListColor::VERT
        ];

        // Tableau de toutes les couleurs disponibles
        $allColors = TaskListColor::cases();
        $colorIndex = 0;

        foreach ($taskLists as $taskList) {
            if (array_key_exists($taskList->getNom(), $defaultColors)) {
                $taskList->setColor($defaultColors[$taskList->getNom()]);
            } else {
                // Assigner une couleur dans l'ordre pour les colonnes personnalisées
                $taskList->setColor($allColors[$colorIndex % count($allColors)]);
                $colorIndex++;
            }
        }

        $entityManager->flush();
    }
}

// Commenté pour éviter les conflits avec la nouvelle version
// Cette classe est une ancienne version du repository TaskListRepository.
//version avant le 20/08/2025

// class TaskListRepository extends ServiceEntityRepository
// {
//     public function __construct(ManagerRegistry $registry)
//     {
//         parent::__construct($registry, TaskList::class);
//     }
    

//     /**
//      * Trouve les colonnes d'un project avec leurs tâches
//      * 
//      * @param Project $project Le project concerné
//      * @return TaskList[] Retourne un tableau d'objets TaskList avec leurs tâches
//      */
//     public function findByProjectWithTasks(Project $project): array
//     {
//         return $this->createQueryBuilder('tl')
//             ->leftJoin('tl.tasks', 't')
//             ->where('tl.project = :project')
//             ->setParameter('project', $project)
//             ->orderBy('tl.positionColumn', 'ASC')
//             ->addOrderBy('t.position', 'ASC')
//             ->getQuery()
//             ->getResult();
//     }

   // /**
    //  * Récupère la position max des colonnes pour un projet
    //  */
    // public function findMaxPositionByProject(Project $project): int
    // {
    //     $max = $this->createQueryBuilder('tl')
    //         ->select('MAX(tl.positionColumn)')
    //         ->andWhere('tl.project = :p')->setParameter('p', $project)
    //         ->getQuery()
    //         ->getSingleScalarResult();

    //     return (int)($max ?? 0);
    // }
