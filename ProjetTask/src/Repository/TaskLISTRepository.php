<?php

namespace App\Repository;

use App\Entity\TaskList;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\TaskListColor;

/**
 * @extends ServiceEntityRepository<TaskList>
 */
class TaskListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskList::class);
    }

    /**
     * Crée les colonnes par défaut pour un nouveau projet
     */
    public function createDefaultColumns(Project $project): void
    {
        $defaultColumns = [
            ['nom' => 'À faire', 'couleur' => 'VERT'],
            ['nom' => 'En cours', 'couleur' => 'JAUNE'],
            ['nom' => 'En révision', 'couleur' => 'ORANGE'],
            ['nom' => 'Terminé', 'couleur' => 'VERT']
        ];

        $em = $this->getEntityManager();

        foreach ($defaultColumns as $index => $columnData) {
            $taskList = new TaskList();
            $taskList->setNom($columnData['nom']);
            $taskList->setPositionColumn($index + 1);
            $taskList->setProject($project);

            $couleurEnum = TaskListColor::from($columnData['couleur']);
            $taskList->setCouleur($couleurEnum);

            $em->persist($taskList);
        }

        $em->flush();
    }

    /**
     * Trouve les colonnes par projet
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('tl')
            ->where('tl.project = :project')
            ->setParameter('project', $project)
            ->orderBy('tl.positionColumn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la position maximale dans un projet
     */
    public function findMaxPositionByProject(Project $project): int
    {
        $result = $this->createQueryBuilder('tl')
            ->select('MAX(tl.positionColumn)')
            ->where('tl.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Trouve les colonnes avec leurs tâches
     */
    public function findByProjectWithTasks(Project $project): array
    {
        return $this->createQueryBuilder('tl')
            ->leftJoin('tl.tasks', 't')
            ->leftJoin('t.assignedUsers', 'au')
            ->addSelect('t', 'au')
            ->where('tl.project = :project')
            ->setParameter('project', $project)
            ->orderBy('tl.positionColumn', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réorganise les colonnes selon un nouvel ordre
     */
    public function reorderColumns(Project $project, array $newOrder): void
    {
        $em = $this->getEntityManager();

        foreach ($newOrder as $position => $columnId) {
            $column = $this->find($columnId);
            if ($column && $column->getProject() === $project) {
                $column->setPositionColumn($position + 1);
                $em->persist($column);
            }
        }

        $em->flush();
    }

    /**
     * Réorganise les positions après suppression d'une colonne
     */
    public function reorganizePositions(Project $project): void
    {
        $columns = $this->findByProject($project);
        $em = $this->getEntityManager();

        foreach ($columns as $index => $column) {
            $column->setPositionColumn($index + 1);
            $em->persist($column);
        }

        $em->flush();
    }

    /**
     * Trouve les colonnes avec le nombre de tâches pour les statistiques
     */
    public function findWithTaskCounts(Project $project): array
    {
        return $this->createQueryBuilder('tl')
            ->leftJoin('tl.tasks', 't')
            ->addSelect('COUNT(t.id) as taskCount')
            ->where('tl.project = :project')
            ->setParameter('project', $project)
            ->groupBy('tl.id')
            ->orderBy('tl.positionColumn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour automatiquement les couleurs de toutes les colonnes d'un projet
     */
    public function updateAutoColorsForProject(Project $project): void
    {
        $columns = $this->findByProject($project);
        $em = $this->getEntityManager();

        foreach ($columns as $column) {
            $column->updateAutoColor();
            $em->persist($column);
        }

        $em->flush();
    }

    /**
     * Trouve les colonnes avec des tâches en retard
     */
    public function findColumnsWithOverdueTasks(Project $project): array
    {
        return $this->createQueryBuilder('tl')
            ->leftJoin('tl.tasks', 't')
            ->where('tl.project = :project')
            ->andWhere('t.dateButoir < :now')
            ->andWhere('t.statut != :completed')
            ->setParameter('project', $project)
            ->setParameter('now', new \DateTime())
            ->setParameter('completed', 'TERMINE')
            ->groupBy('tl.id')
            ->orderBy('tl.positionColumn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les statistiques de couleur pour un projet
     */
    public function getColorStatsForProject(Project $project): array
    {
        $columns = $this->findByProject($project);
        $stats = [
            TaskListColor::VERT->value => 0,
            TaskListColor::JAUNE->value => 0,
            TaskListColor::ORANGE->value => 0,
            TaskListColor::ROUGE->value => 0,
        ];

        foreach ($columns as $column) {
            $column->updateAutoColor(); // Mise à jour automatique
            if ($column->getCouleur()) {
                $stats[$column->getCouleur()->value]++;
            }
        }

        return $stats;
    }

    /**
     * Trouve la colonne avec le plus de retard dans un projet
     */
    public function findMostDelayedColumn(Project $project): ?TaskList
    {
        $columns = $this->findByProject($project);
        $mostDelayed = null;
        $maxDelay = 0;

        foreach ($columns as $column) {
            $overdueCount = $column->getOverdueCount();
            if ($overdueCount > $maxDelay) {
                $maxDelay = $overdueCount;
                $mostDelayed = $column;
            }
        }

        return $mostDelayed;
    }
}










































