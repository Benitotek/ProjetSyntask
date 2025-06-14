<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }
    public function findByAssignedUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.assignedUsers', 'u')
            ->where('u = :user')
            ->setParameter('user', $user)
            ->orderBy('t.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->leftJoin('t.taskList', 'tl')
            ->addSelect('tl')
            ->orderBy('tl.positionColumn', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOverdue(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.dateButoir < :now')
            ->andWhere('t.statut != :completed')
            ->setParameter('now', new \DateTime())
            ->setParameter('completed', Task::STATUT_TERMINE)
            ->orderBy('t.dateButoir', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countOverdueByUser(User $user): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->join('t.assignedUsers', 'u')
            ->where('u = :user')
            ->andWhere('t.dateButoir < :now')
            ->andWhere('t.statut != :completed')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('completed', Task::STATUT_TERMINE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.statut = :status')
            ->setParameter('status', $status)
            ->orderBy('t.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPriority(string $priority): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.priorite = :priority')
            ->setParameter('priority', $priority)
            ->orderBy('t.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentTasks(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findTasksWithDeadlineApproaching(int $days = 3): array
    {
        $deadline = new \DateTime();
        $deadline->modify("+{$days} days");

        return $this->createQueryBuilder('t')
            ->where('t.dateButoir BETWEEN :now AND :deadline')
            ->andWhere('t.statut != :completed')
            ->setParameter('now', new \DateTime())
            ->setParameter('deadline', $deadline)
            ->setParameter('completed', Task::STATUT_TERMINE)
            ->orderBy('t.dateReelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTaskStatsByProject(Project $project): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('t.statut, COUNT(t.id) as count')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->groupBy('t.statut')
            ->getQuery()
            ->getResult();

        $stats = [
            Task::STATUT_EN_ATTENTE => 0,
            Task::STATUT_EN_COURS => 0,
            Task::STATUT_TERMINE => 0,
        ];

        foreach ($result as $row) {
            $stats[$row['statut']] = (int) $row['count'];
        }

        return $stats;
    }
}


    //    /**
    //     * @return Task[] Returns an array of Task objects
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

//    public function findOneBySomeField($value): ?Task
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
