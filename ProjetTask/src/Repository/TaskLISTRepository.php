<?php

namespace App\Repository;

use App\Entity\TaskList;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\TaskListColor;


class TaskListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskList::class);
    }
    // V2 date changement 02/07/2025 

    /**
     * Trouve les colonnes d'un project avec leurs tâches
     * 
     * @param Project $project Le project concerné
     * @return TaskList[] Retourne un tableau d'objets TaskList avec leurs tâches
     */
    public function findByProjectWithTasks(Project $project): array
    {
        return $this->createQueryBuilder('tl')
            ->leftJoin('tl.tasks', 't')
            ->where('tl.project = :project')
            ->setParameter('project', $project)
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
    // V2 date changement 02/07/2025 VS mais manque a revoir

    // /**
}



// Version 1.0 date changement 02/07/2025 VS mais manque a revoir
    // /**
    //  * Crée une nouvelle instance de TaskListRepository
    //  */
    // public static function create(ManagerRegistry $registry): self
    // {
    //     return new self($registry);
    // }

    // /**
    //  * Trouve une colonne par son ID et son project
    //  */
    // public function findOneByIdAndProject(int $id, Project $project): ?TaskList
    // {
    //     return $this->createQueryBuilder('tl')
    //         ->where('tl.id = :id')
    //         ->andWhere('tl.project = :project')
    //         ->setParameter('id', $id)
    //         ->setParameter('project', $project)
    //         ->getQuery()
    //         ->getOneOrNullResult();
    // }
//     /**
//      * Crée les colonnes par défaut pour un nouveau project
//      */
//     public function createDefaultColumns(Project $project): void
//     {
//         $defaultColumns = [
//             ['nom' => 'À faire', 'couleur' => 'VERT', 'description' => 'Tâches à réaliser'],
//             ['nom' => 'En cours', 'couleur' => 'JAUNE', 'description' => 'Tâches en cours de réalisation'],
//             ['nom' => 'En révision', 'couleur' => 'ORANGE', 'description' => 'Tâches à vérifier'],
//             ['nom' => 'Terminé', 'couleur' => 'VERT', 'description' => 'Tâches complétées']
//         ];

//         $em = $this->getEntityManager();

//         foreach ($defaultColumns as $index => $columnData) {
//             $taskList = new TaskList();
//             $taskList->setNom($columnData['nom']);
//             $taskList->setPositionColumn($index + 1);
//             $taskList->setProject($project);

//             $couleurEnum = TaskListColor::tryfrom($columnData['couleur']);
//             $taskList->setCouleur($couleurEnum);

//             $em->persist($taskList);
//         }

//         $em->flush();
//     }

//     /**
//      * Trouve les colonnes par project
//      */
//     public function findByProject(Project $project): array
//     {
//         return $this->createQueryBuilder('tl')
//             ->where('tl.project = :project')
//             ->setParameter('project', $project)
//             ->orderBy('tl.positionColumn', 'ASC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Trouve la position maximale dans un project
//      */
//     public function findMaxPositionByProject(Project $project): int
//     {
//         $result = $this->createQueryBuilder('tl')
//             ->select('MAX(tl.positionColumn)')
//             ->where('tl.project = :project')
//             ->setParameter('project', $project)
//             ->getQuery()
//             ->getSingleScalarResult();

//         return $result ?? 0;
//     }

//     /**
//      * Trouve les colonnes avec leurs tâches
//      */
//     public function findByProjectWithTasks(Project $project): array
//     {
//         return $this->createQueryBuilder('tl')
//             ->leftJoin('tl.tasks', 't')
//             ->leftJoin('t.assignedUsers', 'au')
//             ->addSelect('t', 'au')
//             ->where('tl.project = :project')
//             ->setParameter('project', $project)
//             ->orderBy('tl.positionColumn', 'ASC')
//             ->addOrderBy('t.position', 'ASC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Réorganise les colonnes selon un nouvel ordre
//      */
//     public function reorderColumns(Project $project, array $newOrder): void
//     {
//         $em = $this->getEntityManager();

//         foreach ($newOrder as $position => $columnId) {
//             $column = $this->find($columnId);
//             if ($column && $column->getProject() === $project) {
//                 $column->setPositionColumn($position + 1);
//                 $em->persist($column);
//             }
//         }

//         $em->flush();
//     }

//     /**
//      * Réorganise les positions après suppression d'une colonne
//      */
//     public function reorganizePositions(Project $project): void
//     {
//         $columns = $this->findByProject($project);
//         $em = $this->getEntityManager();

//         foreach ($columns as $index => $column) {
//             $column->setPositionColumn($index + 1);
//             $em->persist($column);
//         }

//         $em->flush();
//     }

//     /**
//      * Trouve les colonnes avec le nombre de tâches pour les statistiques
//      */
//     public function findWithTaskCounts(Project $project): array
//     {
//         return $this->createQueryBuilder('tl')
//             ->leftJoin('tl.tasks', 't')
//             ->addSelect('COUNT(t.id) as taskCount')
//             ->where('tl.project = :project')
//             ->setParameter('project', $project)
//             ->groupBy('tl.id')
//             ->orderBy('tl.positionColumn', 'ASC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Met à jour automatiquement les couleurs de toutes les colonnes d'un project
//      */
//     public function updateAutoColorsForProject(Project $project): void
//     {
//         $columns = $this->findByProject($project);
//         $em = $this->getEntityManager();

//         foreach ($columns as $column) {
//             $column->updateAutoColor();
//             $em->persist($column);
//         }

//         $em->flush();
//     }

//     /**
//      * Trouve les colonnes avec des tâches en retard
//      */
//     public function findColumnsWithOverdueTasks(Project $project): array
//     {
//         return $this->createQueryBuilder('tl')
//             ->leftJoin('tl.tasks', 't')
//             ->where('tl.project = :project')
//             ->andWhere('t.dateButoir < :now')
//             ->andWhere('t.statut != :completed')
//             ->setParameter('project', $project)
//             ->setParameter('now', new \DateTime())
//             ->setParameter('completed', 'TERMINE')
//             ->groupBy('tl.id')
//             ->orderBy('tl.positionColumn', 'ASC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Calcule les statistiques de couleur pour un project
//      */
//     public function getColorStatsForProject(Project $project): array
//     {
//         $columns = $this->findByProject($project);
//         $stats = [
//             TaskListColor::VERT->value => 0,
//             TaskListColor::JAUNE->value => 0,
//             TaskListColor::ORANGE->value => 0,
//             TaskListColor::ROUGE->value => 0,
//         ];

//         foreach ($columns as $column) {
//             $column->updateAutoColor(); // Mise à jour automatique
//             if ($column->getCouleur()) {
//                 $stats[$column->getCouleur()->value]++;
//             }
//         }

//         return $stats;
//     }

//     /**
//      * Trouve la colonne avec le plus de retard dans un project
//      */
//     public function findMostDelayedColumn(Project $project): ?TaskList
//     {
//         $columns = $this->findByProject($project);
//         $mostDelayed = null;
//         $maxDelay = 0;

//         foreach ($columns as $column) {
//             $overdueCount = $column->getOverdueCount();
//             if ($overdueCount > $maxDelay) {
//                 $maxDelay = $overdueCount;
//                 $mostDelayed = $column;
//             }
//         }

//         return $mostDelayed;
//     }
// }
