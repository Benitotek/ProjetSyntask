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

    /**
     * La position max des colonnes pour insertion en fin
     */
    public function findMaxPositionByProject(Project $project): int
    {
        $max = $this->createQueryBuilder('tl')
            ->select('MAX(tl.positionColumn)')
            ->andWhere('tl.project = :p')->setParameter('p', $project)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)($max ?? 0);
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
                $taskList->setCouleur($defaultColors[$taskList->getNom()]);
            } else {
                // Assigner une couleur dans l'ordre pour les colonnes personnalisées
                $taskList->setCouleur($allColors[$colorIndex % count($allColors)]);
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
