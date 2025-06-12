<?php

namespace App\Repository;

use App\Entity\TaskLIST;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskLIST>
 */
class TaskLISTRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskList::class);
    }

     public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('tl')
            ->where('tl.project = :project')
            ->setParameter('project', $project)
            ->orderBy('tl.positionColumn', 'ASC')
            ->getQuery()
            ->getResult();
    }

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

    public function findByProjectWithTasks(Project $project): array
    {
        return $this->createQueryBuilder('tl')
            ->leftJoin('tl.tasks', 't')
            ->addSelect('t')
            ->where('tl.project = :project')
            ->setParameter('project', $project)
            ->orderBy('tl.positionColumn', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

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
}

//    /**
//     * @return TaskLIST[] Returns an array of TaskLIST objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TaskLIST
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

